# DinkyMetrics — Arbeitsplan

Basis: [joomla-dinkymetrics-module-SPEC.md](joomla-dinkymetrics-module-SPEC.md)
(Arbeitstitel dort `mod_keyfigures` — wird durchgängig zu `mod_dinkymetrics`).
Dieses Dokument hier ist die verbindliche Fassung, wo es von der Spec abweicht.

Konventionsreferenz: `P:\dev\dinkytags` (`plg_system_dinkytags`) und
`P:\dev\dinkygallery` (`plg_content_dinkygallery`). Übernommen werden Repo-Layout,
Manifest-Aufbau, `services/provider.php`-Muster, Helper-Schnitt, Phing-`build.xml`
inkl. `update.xml`-Erzeugung, Sprachdatei-Disziplin, `.docker/`-Teststack und die
Doku-Trias SPEC / WORKPLAN / ARCHITECTURE in `.doc/`. **Neu gegenüber beiden:**
dies ist ein **Modul**, kein Plugin — also `ModuleDispatcherFactory` + `HelperFactory`
+ `tmpl/`.

Architekturreferenz für das Modulgerüst: Joomla-Core `modules/mod_articles`
(Branch `6.1-dev`) — Manifest, `services/provider.php` und `Dispatcher` sind dort
gegengeprüft.

---

## Festlegungen

| Aspekt | Wert | Begründung |
|---|---|---|
| Element / Paket | `mod_dinkymetrics`, `type="module"`, `client="site"`, `method="upgrade"` | Repo-/Projektname; Pflichtenheft §14 überließ den Namen dem Maintainer, entschieden 2026-09-03 |
| Namespace | `<namespace path="src">TheLoom\Module\DinkyMetrics</namespace>`; Klassen liegen unter `TheLoom\Module\DinkyMetrics\Site\…` | analog `TheLoom\Plugin\System\DinkyTags`; das `Site`-Segment fügt Joomlas Modul-Autoloader anhand des Clients ein (wie Core `Joomla\Module\Articles\Site\…`) |
| Sprach-Präfix | `MOD_DINKYMETRICS_*` | Joomla-Konvention Site-Modul |
| Lizenz | **GPLv3-or-later** (Pflichtenheft §3 nennt v2+; bewusst angehoben) | Repo enthält bereits `LICENSE` = GPLv3; deckungsgleich mit DinkyTags/DinkyGallery. GPLv3+-Header in jede PHP-/JS-/CSS-Datei |
| Autor-Metadaten | The Loom / Stefan Schulz, `schulz@the-loom.de`, `https://www.the-loom.de` | analog Schwesterprojekte |
| Ziel | Joomla 5.1+ / 6.x, **PHP 8.1+** | Pflichtenheft §3 nennt 8.1–8.4 ausdrücklich; abweichend von DinkyGallery (8.2). Code vermeidet 8.2-only-Syntax, `php_minimum` in `update.xml` = `8.1` |
| Repo-Layout | Modul-Dateien im Repo-**Root** (Manifest `mod_dinkymetrics.xml` an der Wurzel); die vorhandene `.gitignore` ist eine Joomla-Root-Ignore-Liste → Entwicklung in echter Joomla-Installation, nur Modul-Dateien getrackt | wie DinkyTags/DinkyGallery. Weicht von Pflichtenheft §11 ab (dort Modul in einem `mod_keyfigures/`-Unterordner) — die Baumstruktur bleibt identisch, nur ohne den zusätzlichen Wrapper |
| Params | **inline im `mod_dinkymetrics.xml`** (`<config><fields name="params">`), kein separates `config.xml` | analog DinkyTags/DinkyGallery |
| Assets | `media/mod_dinkymetrics/` mit `css/dinkymetrics.css`, `js/dinkymetrics.js`, **`joomla.asset.json`**; Registrierung im Layout | Pflichtenheft §5/§6. `css/`- und `js/`-Unterordner sind Pflicht (s. Risiko R4) |
| Opt-in-CSS | **ja**, minimal (Flex-Reihe, `gap`, zentrierte Items, `tabular-nums`), per WebAssetManager abwählbar | Pflichtenheft §14-Empfehlung |
| `years_since` mit `unit` | **behalten** (`years` \| `months` \| `days`) | entschieden 2026-09-03; Nachrüsten wäre teurer als Mitnehmen |
| Sprachen v1 | **en-GB + de-DE** | Pflichtenheft §8 fordert nur en-GB; Hausstandard liefert beide, und ohne de-DE zeigt eine deutsche Admin-Oberfläche rohe Keys |
| Tests | `composer.json` **nur** `require-dev`: `squizlabs/php_codesniffer` + `joomla/coding-standards` + `phpunit/phpunit`. Unit-Tests für die **reine** Logik (Formatierung, `years_since`); Zähl-Queries über `.docker`-Fixtures + QA-Checkliste statt DB-Mocks | entschieden 2026-09-03 (Pflichtenheft §13 vs. Hausstandard „keine Testsuite") |
| CI | **keine** GitHub-Action in v1 (`phpcs` / `phpunit` / `phing package` lokal) | Pflichtenheft §11 markiert CI als optional; Schwesterprojekte haben keine |
| Build | Phing `build.xml`, Target `package` → `.releases/mod_dinkymetrics-<version>.zip` + `.releases/update.xml` mit sha256/384/512 | Muster DinkyGallery; ersetzt `build/build.sh` + `dist/` aus Pflichtenheft §11 |
| Update-Server | `https://www.the-loom.de/extensions/dinkymetrics/update.xml`, `<updateservers>` von Anfang an im Manifest | Muster DinkyTags/DinkyGallery; kostet durch `build.xml` praktisch nichts (Pflichtenheft §14) |
| Version | Manifest startet bei `1.0.0`, `CHANGELOG.md` mit `## [Unreleased]` (Keep a Changelog / SemVer) | Hausstandard |
| Modul-Cache | `ModuleHelper::moduleCache()` im Dispatcher mit `cachemode = 'id'` und selbstgebautem `modeparams`-Hash | s. Entscheidung E1 |

---

## Verzeichnisbaum (Ziel)

```
dinkymetrics/
├── mod_dinkymetrics.xml              Manifest: namespace, files, media, languages, config, updateservers
├── services/provider.php             DI: ModuleDispatcherFactory + HelperFactory + Module
├── src/
│   ├── Dispatcher/Dispatcher.php     getLayoutData(): Kennzahlen auflösen, Modul-Cache, leere Ausgabe kurzschließen
│   └── Helper/
│       ├── DinkyMetricsHelper.php    öffentliche API: getFigures(), resolve() (switch über `source`), Link-Auflösung
│       ├── Counter.php               content_count / category_count — Query-Aufbau, ausschließlich gebunden
│       ├── Formatter.php             Zahl → String (Locale, Gruppierung, Dezimalstellen) — rein, unit-getestet
│       └── Elapsed.php               years_since (years/months/days) — rein, unit-getestet
├── tmpl/
│   ├── default.php                   Markup-Vertrag §5
│   └── default.xml                   Layout-Metadaten
├── media/mod_dinkymetrics/
│   ├── joomla.asset.json             mod_dinkymetrics.script / mod_dinkymetrics.style
│   ├── css/dinkymetrics.css          opt-in
│   └── js/dinkymetrics.js            Zählanimation
├── language/{en-GB,de-DE}/mod_dinkymetrics.ini + .sys.ini
├── tests/
│   ├── Unit/FormatterTest.php · ElapsedTest.php
│   └── parity/parity.mjs + fixtures.json     Server-/JS-Formatierungsparität
├── build.xml · composer.json · phpunit.xml.dist · phpcs.xml.dist
├── .docker/                          Wegwerf-Joomla (J5 primär, J6 für die ZIP-Abnahme) — nicht getrackt
├── .doc/                             SPEC (vorhanden) · WORKPLAN · ARCHITECTURE
├── .releases/                        Build-Output (generiert)
└── README.md · CHANGELOG.md · LICENSE · LICENSE.txt
```

---

## Phase 0 — Repo-Gerüst & Werkzeuge

1. Baum oben anlegen (leere Dateien mit Lizenz-Header genügen zunächst).
2. `README.md` von Einzeiler auf Gerüst erweitern (Vollfassung in Phase 12);
   `CHANGELOG.md` mit `## [Unreleased]`.
3. `LICENSE` (GPLv3) ist vorhanden — zusätzlich `LICENSE.txt` als versandte Kopie
   ablegen und in `.gitignore` per `!/LICENSE.txt` freistellen (wie DinkyTags).
4. `.gitignore` ergänzen: `/.releases`, `/.docker`, `/vendor`, `/composer.lock`,
   `/.phpunit.cache` (`/.idea`, `/.doc` sind schon drin).
   `.editorconfig` + `.gitattributes` aus DinkyGallery übernehmen.
5. `composer.json` (nur `require-dev`), `phpcs.xml.dist` (Ruleset `Joomla`, geprüft
   werden `src/`, `tmpl/`, `services/`), `phpunit.xml.dist` (Suite `tests/Unit`).
6. **`.docker/`-Stack** aus DinkyGallery adaptieren: `dinkymetrics.localhost`
   (Joomla 5, Symlink-Entwicklung) + `dinkymetrics6.localhost` (Joomla 6,
   Installation aus dem gebauten ZIP). `setup.sh` symlinkt `mod_dinkymetrics.xml`,
   `src/`, `services/`, `tmpl/`, `language/` nach `modules/mod_dinkymetrics/` und
   `media/mod_dinkymetrics/` nach `media/`, dann `extension:discover`.
7. **`fixtures.sql`** — die Testdatenmatrix ist hier der eigentliche Aufwand, weil
   alle Zähl-Abnahmen daran hängen:
   - Kategoriebaum `metrics-root` → `child-a` (→ `grand-a1`), `child-b`, `leer`
     (ohne Beiträge), plus eine unveröffentlichte Kategorie.
   - Artikel je Kategorie mit den Varianten: `state = 1 / 0 / -2`, `publish_up` in
     der Zukunft, `publish_down` in der Vergangenheit, `featured = 0 / 1`,
     `language = 'de-DE' / 'en-GB' / '*'`, `access = 1` (Public) / `2` (Registered).
   - Ein Modul-Datensatz mit einer `figures`-Subform, die alle vier Quellen
     abdeckt, plus eine zweite Instanz mit abweichenden Animations-Parametern
     (QA-Punkt 12).
   - Die erwarteten Zählwerte als Kommentar direkt in `fixtures.sql` — sie sind die
     Sollwerte der QA-Checkliste.

**Ende der Phase:** `phpcs` / `phpunit` laufen (leer), Stack startet, Joomla kennt
das Modul noch nicht.

## Phase 1 — Manifest & Bootstrap (Ziel: installierbar)

1. **`mod_dinkymetrics.xml`**: `<extension type="module" client="site" method="upgrade">`,
   Metadaten, `<namespace path="src">TheLoom\Module\DinkyMetrics</namespace>`,

   ```xml
   <files>
       <folder module="mod_dinkymetrics">services</folder>
       <folder>src</folder>
       <folder>tmpl</folder>
   </files>
   <media destination="mod_dinkymetrics" folder="media/mod_dinkymetrics">
       <folder>css</folder>
       <folder>js</folder>
       <filename>joomla.asset.json</filename>
   </media>
   ```

   plus `<languages folder="language">` (en-GB + de-DE, je `.ini` und `.sys.ini`)
   und `<updateservers>`.
2. **`services/provider.php`** exakt nach Core-Muster:
   `registerServiceProvider(new ModuleDispatcherFactory('\\TheLoom\\Module\\DinkyMetrics'))`,
   `new HelperFactory('\\TheLoom\\Module\\DinkyMetrics\\Site\\Helper')`,
   `new Module()`.
3. **`src/Dispatcher/Dispatcher.php`**: `extends AbstractModuleDispatcher implements
   HelperFactoryAwareInterface`, `use HelperFactoryAwareTrait`, vorerst
   `getLayoutData()` = `parent::getLayoutData()`.
4. **`tmpl/default.php`** vorerst leer (`return;`), `tmpl/default.xml` mit
   `<layout title="MOD_DINKYMETRICS_LAYOUT_DEFAULT">`.

**Ende der Phase:** Modul lässt sich anlegen und in einer Position speichern,
die Seite rendert unverändert.

## Phase 2 — Parameter-Formular + Sprachdateien

Gehört inhaltlich zu Phase 1, damit die Installation von Anfang an nützlich ist.

1. **Subform `figures`** — `type="subform" multiple="true"
   layout="joomla.form.field.subform.repeatable-table"`, Felder exakt nach
   Pflichtenheft §4.2 (`label`, `source`, `value`, `date`, `unit`, `category`,
   `parent`, `include_children`, `state`, `featured`, `skip_empty`, `prefix`,
   `suffix`, `link`) mit den dortigen `showon`-Ausdrücken.
   **Vorher Spike R1/R2** (s. Risiken) — davon hängt ab, ob das Tabellen- oder das
   Section-Layout genommen wird.
2. **Globale Params** (Fieldsets `basic` / `advanced`): `animate`,
   `animate_trigger`, `animate_duration`, `animate_easing`, `animate_from`,
   `animate_threshold`, `respect_reduced_motion`, `format_grouping`,
   `format_decimals`, `format_locale`, `count_all_access`, `debug`.
   Radios als `layout="joomla.form.field.radio.switcher"` mit `JNO` / `JYES` —
   Hausstandard.
3. **Sprachdateien**: jeder im XML referenzierte Key existiert in **beiden**
   Sprachen, sonst zeigt das Formular den rohen Key. `.sys.ini` mit
   `MOD_DINKYMETRICS` + `MOD_DINKYMETRICS_XML_DESCRIPTION`.
4. `state` / `featured` / `unit` / `easing` / `trigger` bekommen im Helper eine
   **Enum-Whitelist**; unbekannte Werte fallen auf den Default zurück
   (Pflichtenheft §9).

**Ende der Phase:** Formular vollständig bedienbar, Werte werden gespeichert,
Ausgabe weiterhin leer.

## Phase 3 — `Helper/Formatter.php` (rein, testbar)

- `format(float|int $value, string $locale, int $decimals, bool $grouping): string`.
- Primärpfad `NumberFormatter` (ext-intl) — deckungsgleich mit `Intl.NumberFormat`
  im Browser. Fallback ohne intl: Separator-Tabelle für die gängigen Locales +
  `number_format()`, dokumentierte Einschränkung (Risiko R5).
- `resolveLocale(Registry $params, CMSApplication $app): string` —
  `format_locale`, sonst Sprach-Tag der Site (`de-DE`), auf BCP-47 validiert.
- Unit-Tests: `1234.5` → `1.234,5` (de-DE, Gruppierung, 1 Dezimale) und `1,234.5`
  (en-GB); ohne Gruppierung; 0 Dezimalstellen; negative Werte; `0`.

## Phase 4 — `Helper/Elapsed.php` (rein, testbar)

- `since(DateTimeInterface $date, string $unit, DateTimeZone $tz): int` über
  `DateTime::diff`, **floor**, Zeitzone = Site-Zeitzone.
- Zukunftsdatum → `0` plus `Log::add(…, Log::WARNING, 'mod_dinkymetrics')`, keine
  Fehlermeldung nach außen.
- Unit-Tests: Jahreswechsel- und Schaltjahrgrenze (29.02. → 28.02.),
  `months` / `days`, Zukunftsdatum, identisches Datum → `0`.

## Phase 5 — `Helper/Counter.php`: `content_count`

Eine `SELECT COUNT(*)`-Query auf `#__content AS a`, alles gebunden, `quoteName()`
durchgängig:

- `include_children=yes`: Join `#__categories AS cc ON cc.id = a.catid` und
  `#__categories AS root ON root.id = :catid` mit `cc.lft >= root.lft AND
  cc.rgt <= root.rgt` (Nested Set) — **eine** Query, kein Vorab-Lookup.
  `no` → `a.catid = :catid`. Ohne `category` → kein Kategoriefilter.
- `state=published`: `a.state = 1 AND (a.publish_up IS NULL OR a.publish_up <= :now)
  AND (a.publish_down IS NULL OR a.publish_down > :now)`;
  `published_unpublished`: `a.state IN (0,1)`; `any`: kein Statusfilter.
- `featured`: `only` → `= 1`, `exclude` → `= 0`, `any` → kein Filter.
- Sprache: nur wenn `Multilanguage::isEnabled()` → `a.language IN (:lang, '*')`.
- Access: sofern `count_all_access = no` → `whereIn('a.access',
  $app->getIdentity()->getAuthorisedViewLevels())`.
- Kategoriestatus: s. Entscheidung E2.

Verifikation gegen die `fixtures.sql`-Sollwerte (kein DB-Mock).

## Phase 6 — `Helper/Counter.php`: `category_count`

- `#__categories AS c`, `c.extension = :extension` (Default `com_content`).
- `include_children=no` (Default) → `c.parent_id = :parent`;
  `yes` → Nested-Set-Grenzen der Elternkategorie, **ohne** die Wurzel selbst.
- `state=published` → `c.published = 1`.
- `skip_empty=yes` → `AND EXISTS (SELECT 1 FROM #__content ct WHERE ct.catid = c.id
  AND ct.state = 1 …)` — korrelierte Unterabfrage statt N+1.

## Phase 7 — `DinkyMetricsHelper` + Dispatcher + Cache

1. `resolve(array $figure, Registry $params, SiteApplication $app): int|float` —
   `switch` über `source`, öffentlich und als überschreibbar dokumentiert
   (Erweiterungspunkt nach Pflichtenheft §4.1).
2. `getFigures(Registry $params, SiteApplication $app): array` — iteriert die
   Subform-Zeilen, überspringt Zeilen ohne `label` oder mit unauflösbarer Quelle
   (bei `debug=1` je Skip eine Log-Zeile) und liefert je Zeile ein fertiges Array
   (`raw`, `formatted`, `prefix`, `suffix`, `label`, `href`, `external`, `aria`).
3. **Link-Auflösung**: intern über `Route::_()`, extern via `filter_var` +
   `InputFilter` geprüft (`http` / `https` / `mailto` erlaubt, `javascript:` und
   Konsorten raus) und mit `rel="noopener noreferrer"`.
4. **Dispatcher**: `getLayoutData()` löst die Kennzahlen über
   `ModuleHelper::moduleCache()` auf (Muster `mod_articles`) und setzt
   `$data['figures']`; bei leerem Ergebnis gibt das Layout nichts aus (das übliche
   Modul-Chrome unterdrückt dann auch den Wrapper — QA-Punkt 2, s. Risiko R7).

## Phase 8 — Layout `tmpl/default.php` (Markup-Vertrag §5)

- Markup exakt nach Pflichtenheft §5 inklusive `data-*`-Satz, BEM-Klassen,
  `role="list"`, `aria-label` auf `.mod-dinkymetrics__value`,
  `aria-hidden="true"` auf Prefix/Suffix-Spans und dem Kommentar-Trick gegen
  Whitespace zwischen den Spans.
- Klassen-Namensraum `mod-dinkymetrics__*` statt `mod-keyfigures__*` aus der Spec —
  konsistent mit dem Paketnamen; im README als Override-Vertrag dokumentiert.
- Alles escaped über `$this->escape()`; keine Inline-Styles.
- Asset-Registrierung: `$wa->getRegistry()->addExtensionRegistryFile('mod_dinkymetrics')`
  **vor** `useStyle()` / `useScript()` (Risiko R3); das Script nur bei `animate=yes`.

**Ende der Phase:** ohne JavaScript stehen alle Endwerte korrekt formatiert im
HTML (QA-Punkt 4).

## Phase 9 — `media/mod_dinkymetrics/js/dinkymetrics.js`

Vanilla ES2019+, keine Bibliothek, pro `.mod-dinkymetrics`-Instanz initialisiert:

- `prefers-reduced-motion: reduce` → **immer** abbrechen (bindend und unabhängig von
  `respect_reduced_motion`; der Schalter kann nur zusätzlich global abschalten).
- Trigger `in-view` (IntersectionObserver mit `data-threshold`, danach
  `unobserve`) bzw. `on-load`; fehlt `IntersectionObserver`, wird wie `on-load`
  behandelt.
- Tween per `requestAnimationFrame` von `data-from` nach `data-target` über
  `data-duration` mit eigener Easing-Funktion (`linear`, `ease-out`,
  `ease-in-out`); pro Frame `Intl.NumberFormat(locale, …)`, geschrieben wird nur
  `.mod-dinkymetrics__number.textContent`. Prefix/Suffix bleiben unangetastet.
- Der Abschluss setzt exakt den serverseitigen Endwert (kein Rundungsdrift);
  Idempotenz über `dataset.done = '1'`; `target === from` → sofort fertig.

## Phase 10 — `media/mod_dinkymetrics/css/dinkymetrics.css` (opt-in)

Minimal: `display:flex`, `flex-wrap`, `gap`, zentrierte Items, `list-style:none`,
`font-variant-numeric: tabular-nums` auf `.mod-dinkymetrics__number`. Keine Farben,
keine Schriftgrößen, logische Eigenschaften (`margin-inline`) für RTL.
Selektoren durchgängig unter `.mod-dinkymetrics` scopen — Cassiopeia setzt
`.com-content-article ul{overflow:hidden}` und Ähnliches mit höherer Spezifität
(in DinkyGallery bereits schmerzhaft gelernt).

## Phase 11 — Build & Update-Server

`build.xml` aus DinkyGallery adaptieren (Target `package`, Version aus
`<xmlproperty file="mod_dinkymetrics.xml"/>`, `.releases/`, sha256/384/512,
`update.xml` mit `<type>module</type>`, `<client>site</client>`,
`targetplatform (5.(1|2|3|4|5)|6.(0|1|2|3))`, `php_minimum 8.1`).

**Wichtig gegenüber der Vorlage:** die Excludes müssen die Dev-Artefakte dieses
Projekts erfassen — zusätzlich zu `.*`, `build.xml`, `README.md`, `CHANGELOG.md`,
`LICENSE` auch `composer.json`, `composer.lock`, `vendor/**`, `tests/**`,
`phpunit.xml.dist`, `phpcs.xml.dist`. Nach dem ersten Build den ZIP-Inhalt einmal
vollständig auflisten und gegen den Zielbaum prüfen.

## Phase 12 — Dokumentation

- `README.md`: Zweck, Installation, jeder Parameter, der **Markup-Vertrag** und wie
  man ihn per Template-Override (`html/mod_dinkymetrics/default.php`) ersetzt, wie
  man das Opt-in-CSS per WebAssetManager abschaltet, der Hinweis auf
  `min-width` / `tabular-nums` gegen Layout-Sprünge, das Cache-Verhalten
  („errechnete Zahlen aktualisieren sich mit Ablauf des Modul-Caches") und
  „N Kennzahlen = N COUNT-Queries pro ungecachtem Render".
- `.doc/ARCHITECTURE.md` im Stil von DinkyGallery (Dateikarte, Datenfluss, wie man
  eine neue `source` ergänzt).
- `CHANGELOG.md` → `## [1.0.0]`.

## Phase 13 — Abnahme

- `phpcs` fehlerfrei gegen das `Joomla`-Ruleset, `phpunit` grün,
  `node tests/parity/parity.mjs` grün (Server-Fixtures gegen `Intl.NumberFormat`).
- QA-Checkliste Pflichtenheft §13 (Punkte 1–15) auf dem J5-Stack, danach die
  Installations-/Deinstallationspunkte und die Kernpunkte auf dem **J6-Stack aus
  dem gebauten ZIP** — das verifiziert zugleich Manifest und `<media>`-Layout.
- Prüfpunkte, die erfahrungsgemäß kippen: 5 (genau einmal hochzählen),
  7 (reduced motion), 10 (Mehrsprachigkeit), 11 (Cache), 12 (zwei Instanzen),
  15 (CSS abgewählt).

---

## Reihenfolge der Umsetzung

| Slice | Phasen | verifizierbares Ergebnis |
|---|---|---|
| 1 | 0–2 | Modul installiert sich, Parameterformular vollständig, Ausgabe leer |
| 2 | 3–4 | `phpunit` grün; `literal` und `years_since` rendern (Roh-Ausgabe) |
| 3 | 5–7 | alle vier Quellen liefern die Fixture-Sollwerte, Cache greift |
| 4 | 8 | Markup-Vertrag vollständig, ohne JS korrekt (QA 2–4, 8, 9) |
| 5 | 9–10 | Animation + Opt-in-CSS (QA 5–7, 12, 14, 15) |
| 6 | 11–12 | ZIP + `update.xml` + Doku |
| 7 | 13 | Abnahme auf Joomla 5 und 6 |

---

## Risiken & bekannte Fallstricke

- **R1 — `showon` in der Subform.** `showon` innerhalb wiederholbarer Subform-Zeilen
  ist historisch fehleranfällig, besonders im Layout
  `joomla.form.field.subform.repeatable-table`. **Spike vor Phase 2:** eine Zeile mit
  `source` und zwei `showon`-abhängigen Feldern anlegen und in einer *frisch
  hinzugefügten* Zeile prüfen. Fällt der Test durch: auf `repeatable`
  (Section-Layout) ausweichen oder alle Felder zeigen und im Helper ignorieren, was
  nicht zur Quelle passt.
- **R2 — `calendar`- und `category`-Feld in dynamisch hinzugefügten Zeilen.**
  Der Kalender-Picker muss nach „Zeile hinzufügen" initialisiert sein. Im selben
  Spike mitprüfen; Notausgang für `date` wäre `type="text"` mit `YYYY-MM-DD`-Hint.
- **R3 — `joomla.asset.json` wird nicht automatisch geladen.** Ohne
  `$wa->getRegistry()->addExtensionRegistryFile('mod_dinkymetrics')` schlagen
  `useStyle()` / `useScript()` mit „asset not found" fehl (in DinkyGallery erlebt).
- **R4 — `<media>` verlangt `css/`- und `js/`-Unterordner.** Flach abgelegte Dateien
  werden beim Paketieren still verworfen.
- **R5 — `NumberFormatter` vs. `Intl.NumberFormat`.** Ohne `ext-intl` greift der
  Fallback; außerdem können exotische Locales (z. B. `de-CH`, `hi-IN`) zwischen
  ICU-Versionen abweichen. Die Paritätsgarantie wird auf eine dokumentierte
  Locale-Menge (`de-DE`, `en-GB`, `en-US`, `fr-FR`) festgeschrieben und getestet.
- **R6 — Access-abhängige Zahlen und Modul-Cache.** s. Entscheidung E1.
- **R7 — Leere Ausgabe vs. Modul-Chrome.** „Kein leerer Wrapper" funktioniert nur,
  weil die üblichen Chromes (Cassiopeia `card` / `html5`) bei leerem
  `$module->content` früh zurückkehren. Ein Fremdtemplate mit eigenem Chrome kann
  trotzdem eine leere Box rendern — als bekannte Einschränkung ins README.
- **R8 — Joomla 7.** Im Core existiert bereits ein `7.0-dev`-Branch. v1 zielt auf
  5.1+ / 6.x; `targetplatform` bleibt entsprechend eng.

---

## Entscheidungen, die beim Umsetzen anstehen

- **E1 — Cache-Modus bei aktivem Access-Filter.** Pflichtenheft §10 sagt „`static`,
  wenn `count_all_access=yes`, sonst `itemid`". `itemid` schlüsselt aber nach
  Menüpunkt, nicht nach Zugriffsebenen — zwei Benutzer mit verschiedenen Ebenen
  bekämen dieselbe gecachte Zahl. **Vorschlag:** durchgängig `cachemode = 'id'` mit
  selbstgebautem `modeparams`-Hash aus Modul-ID, Params-Hash, Itemid, Sprache und —
  nur wenn der Access-Filter aktiv ist — den `getAuthorisedViewLevels()` des
  Benutzers (Muster `mod_articles`). Erfüllt die Absicht von §10 und ist in beiden
  Konfigurationen korrekt.
- **E2 — Kategoriestatus bei `content_count`.** Das Pflichtenheft filtert nur den
  Artikelstatus. **Vorschlag:** bei `state=published` zusätzlich `cc.published = 1`
  fordern, damit die Zahl der Frontend-Sichtbarkeit entspricht; bei
  `published_unpublished` / `any` ungefiltert. Im README dokumentieren.
- **E3 — Default von `count_all_access`.** §4.1 („Access-Filter Default an") und §10
  („`count_all_access=yes` ist Default") widersprechen sich. **Vorschlag:** §4.1
  gewinnt — Access-Filter an (`count_all_access = no` als Default), weil die
  angezeigte Zahl sonst Inhalte mitzählt, die der Betrachter nicht sehen kann; die
  Cache-Korrektheit stellt E1 sicher. **In Slice 1 so umgesetzt** (Default `no`).
- **E4 — Was `state = any` einschließt.** Das Pflichtenheft definiert `any` als
  „kein Statusfilter", also inklusive Papierkorb und Archiv. **Umgesetzt wie
  spezifiziert**, das Options-Label sagt es aber ausdrücklich („Jeder Status, auch
  Papierkorb"), damit niemand versehentlich gelöschte Beiträge mitzählt. Im README
  wiederholen.
- **E5 — Zwei `include_children`-Felder statt einem.** §4.2 sieht *ein* Feld mit
  `showon="source:content_count[OR]source:category_count"` vor, §4.1 verlangt aber
  unterschiedliche Defaults (`yes` bei `content_count`, `no` bei `category_count`) —
  mit einem Feld nicht darstellbar. **Umgesetzt:** `include_children` (Beiträge,
  Default `yes`) und `include_children_cat` (Kategorien, Default `no`); da immer nur
  eines sichtbar ist, merkt die Redaktion nichts davon.

Alle fünf sind wie vorgeschlagen umsetzbar; sie stehen hier, damit die Abweichung
vom Pflichtenheft-Wortlaut nachvollziehbar bleibt.

---

## Während Slice 1 (Phase 0–2) entschieden & gelernt

- **Coding-Standard: PSR-12 statt `joomla/coding-standards`.** Beide Pakete
  (`joomla/coding-standards`, `joomla/cms-coding-standards`) sind auf Packagist als
  *abandoned* markiert, und ihr `Joomla`-Ruleset ist noch der Joomla-3-Stil (Tabs,
  `new class` ohne Klammern) — es meldet den Core-eigenen Code als fehlerhaft.
  Joomla 6.1-dev selbst lintet nur noch mit `squizlabs/php_codesniffer` gegen ein
  eigenes `ruleset.xml` = **PSR-12** plus Ausnahmen für Altcode. `phpcs.xml.dist`
  macht es genauso, ohne die Altcode-Ausnahmen. `src/`-Dateien brauchen deshalb um
  `\defined('_JEXEC') or die;` das `// phpcs:disable PSR1.Files.SideEffects`-Paar
  wie im Core.
- **Manifest wächst mit dem Code.** `<folder>src</folder>` und der `<media>`-Block
  kommen erst in die `<files>`, wenn es die Verzeichnisse gibt (Phase 3 bzw. 8–10) —
  sonst bricht `extension:discover:install` über fehlende Pfade. Ebenso gibt es
  vorerst keinen `Dispatcher`: `ModuleDispatcherFactory` fällt automatisch auf den
  generischen Modul-Dispatcher zurück, und ein Dispatcher, der nur
  `parent::getLayoutData()` aufruft, wäre toter Code. Er kommt mit Phase 7.
- **Feld `extension` ergänzt.** §4.1 nennt es für `category_count` (Default
  `com_content`), §4.2 listet es nicht. Es ist als Liste mit den vier Kern-Komponenten
  umgesetzt; `parent` und `skip_empty` hängen an `extension:com_content`, weil der
  Kategorie-Picker und die „leere Kategorie"-Prüfung an `com_content` gebunden sind.
- **R1 (showon in der Subform) — erledigt, funktioniert.** Im Backend-Formular
  geprüft: Joomla schreibt die Bedingungen pro Zeile um
  (`jform[params][figures][figures0][source]`), 72 `showon`-Felder bei sechs Zeilen.
  Auch in einer **neu hinzugefügten** Zeile (`figures6`) ist die Bedingung korrekt
  auf die eigene Zeile bezogen, und ein Wechsel der Quelle blendet dort live um
  (`years_since` → Datum und Einheit sichtbar, Wert versteckt). Das
  `repeatable-table`-Layout bleibt.
- **R2 (Kalender in der Subform) — bestätigt und umgangen.** In einer per JS
  hinzugefügten Zeile benennt Joomla `name` und `id` der Eingabefelder um, **nicht**
  aber `id` und `data-inputfield` des Kalender-Buttons — der zeigt weiter auf die
  Vorlagenzeile `figuresX`. Folge: der Picker öffnet sich, das angeklickte Datum
  landet aber nirgends; das Feld bleibt leer, ohne Fehlermeldung. Serverseitig
  gerenderte Zeilen sind korrekt, nur geklonte nicht. **Umgesetzt:** `date` ist ein
  `type="text"` mit `hint="YYYY-MM-DD"` und `pattern`, wie in R2 als Notausgang
  vorgesehen — gleiches Verhalten in jeder Zeile statt stillem Datenverlust in
  neuen. Abweichung von §4.2 (`calendar`), Begründung steht als Kommentar im
  Manifest. Der Helper validiert das Format in Phase 4 ohnehin serverseitig.
- **Sprachdateien: `XML_DESCRIPTION` gehört auch in die `.ini`.** Im
  Modul-Bearbeitungsformular stand die Modulbeschreibung als roher Sprachschlüssel.
  Ursache: `com_modules` lädt dort (`ModuleModel.php:805`) nur
  `mod_dinkymetrics`, **nicht** `mod_dinkymetrics.sys` — die `.sys.ini` sieht nur
  die Modulliste und der Installer. Joomlas eigene Module halten den Schlüssel
  deshalb in beiden Dateien; jetzt hier ebenso.
- **Kein `<languages>`-Block.** Die Sprachdateien bleiben über
  `<folder>language</folder>` im Extension-Ordner (Hausstandard DinkyTags/
  DinkyGallery). Joomla fällt für `.ini` wie `.sys.ini` auf
  `<client>/modules/mod_dinkymetrics/language/` zurück, damit verhalten sich
  Symlink-Entwicklung und installiertes ZIP identisch — mit `<languages>` würden die
  Dateien beim ZIP-Install stattdessen zentral abgelegt und die beiden Wege liefen
  auseinander.
- **`.docker`-Stack läuft.** Joomla 5 auf http://dinkymetrics.localhost/, Modul über
  `extension:discover:install` registriert, Fixtures geladen (Nested Set der
  Kategorien verifiziert: ROOT 0–23, `metrics-root` 11–22, keine Überlappungen).
  Zwei Modul-Instanzen stehen in `sidebar-right`.
- **Leere Ausgabe verifiziert (QA-Punkt 2).** Die Startseite liefert HTTP 200 ohne
  jedes `mod-dinkymetrics`-Markup und ohne leere Card — obwohl beide Instanzen
  veröffentlicht sind und die Position gerendert wird (das Hauptmenü steht dort).
  Risiko R7 verhält sich unter Cassiopeia also wie erwartet.
- **Advanced-Fieldset: nur die fünf Core-Felder deklarieren.** Erster Speicherversuch
  endete in `ChromestyleField::validate() rule 'moduleChrome' missing` — die Regel
  gibt es nicht (registriert ist von den Modul-Regeln nur `ModuleLayoutRule`).
  Kern-Module deklarieren `style`, `module_tag`, `bootstrap_size`, `header_tag`,
  `header_class` überhaupt nicht; `com_modules` ergänzt sie selbst — im gespeicherten
  Params-JSON stehen sie denn auch, ohne dass das Manifest sie kennt. Der Fieldset
  enthält jetzt exakt `layout`, `moduleclass_sfx`, `cache`, `cache_time`, `cachemode`
  wie `mod_articles_news`.
- **`pattern` beim Datumsfeld muss einen Zeitanteil dulden.** Mit
  `[0-9]{4}-[0-9]{2}-[0-9]{2}` blockierte die Client-Validierung das Speichern, weil
  gespeicherte Kalenderwerte als `1996-05-15 00:00:00` vorliegen — genau die Werte,
  die bei einem Umstieg vom `calendar`-Feld in der Datenbank stehen. Das Pattern
  erlaubt jetzt optional `[ T]HH:MM(:SS)`; die Fixtures verwenden reine Datumswerte.
- **Die Subform speichert in jeder Zeile alle Felder.** Auch die per `showon`
  versteckten landen mit ihrem Default im JSON — eine `years_since`-Zeile trägt also
  `include_children`, `featured`, `extension`, `skip_empty` und `state` mit sich.
  `resolve()` darf deshalb **nie** aus dem Vorhandensein eines Schlüssels auf die
  Absicht schließen, sondern ausschließlich über `source` verzweigen. Nebeneffekt:
  E5 (zwei `include_children`-Felder) ist in den Daten unsichtbar.
- **Speicher-Round-Trip bestanden.** Zeile im Formular ergänzt, gespeichert, JSON
  geprüft: sieben Zeilen mit den Schlüsseln `figures0`…`figures6`, Reihenfolge und
  Werte unverändert, `cachemode=id` erhalten, Sonderzeichen korrekt (`≈` als
  `E28988`).
- **Joomlas Kategorie-Beitragszähler ist nicht unser Zähler.** Die Kategorieseite
  zeigt für Child A `Article Count: 7`, unsere Kennzahl wird dort `4` liefern. Joomla
  zählt nur `state = 1`, ohne Start-/Ablaufdatum und ohne Zugriffsebene; §4.1
  verlangt beides. Der Unterschied ist gewollt und gehört ins README, sonst wirkt die
  Modulzahl wie ein Fehler.
- **Fixture-Inhalte sind im Frontend erreichbar.** Drei Menüpunkte: eine Seite mit
  Kategoriebaum, Ausschlussgründen und Sollwerten (Artikel 660), die Kategorieübersicht
  unter `metrics-root` mit Beitragszahlen und der Blog von Child A. Die Sollwerte
  bestätigen sich unabhängig an Joomlas eigener Ausgabe: die Kategorieliste zeigt drei
  direkte Kinder ohne die unveröffentlichte Kategorie, der Child-A-Blog exakt die vier
  zählbaren Beiträge.

---

## Während Slice 2 (Phase 3–4) entschieden & gelernt

- **Rundungsmodus ist die eigentliche Paritätsfalle.** ICU rundet standardmäßig
  kaufmännisch-gerade (half-even), `Intl.NumberFormat` rundet standardmäßig von der
  Null weg (`halfExpand`). `2.5` ohne Nachkommastellen ist damit serverseitig `2` und
  im Browser `3` — sichtbar erst, wenn eine Kennzahl zufällig auf `,5` endet, und dann
  als Zahl, die am Ende der Animation springt. `Formatter` setzt deshalb
  `ROUND_HALFUP` explizit; ein eigener Test deckt `0.5`/`1.5`/`2.5`/`3.5` ab.
- **`intl` fehlt im lokalen PHP-CLI, der Container hat es.** Host: PHP 8.5 ohne intl,
  Container: PHP 8.3 mit ICU 76.1. Beide Wege sind jetzt getestet — lokal läuft die
  Fallback-Tabelle, im Container ICU, und **beide** erfüllen dieselben 25
  Paritätsfälle. `.docker/test.sh` startet die Suite im Container; das ist der
  maßgebliche Lauf, weil PHP 8.3 auch näher an der Zielplattform liegt.
- **Die Paritätsfälle sind über zwei ICU-Versionen stabil.** `cases.json` ist mit
  node/ICU 78.3 erzeugt, PHP prüft dagegen mit ICU 76.1, `parity.mjs` prüft die
  JS-Seite erneut — 25/25 in beiden Richtungen. Die Datei ist damit die
  gemeinsame Wahrheit für Server und Browser; Phase 9 muss sich genau an die eine
  `Intl.NumberFormat`-Zeile in `parity.mjs` halten.
- **`createFromFormat('Y-…')` akzeptiert ein- bis vierstellige Jahre.** Der Tippfehler
  `96-05-15` wurde klaglos zum Jahr 96 n. Chr. und hätte eine Kennzahl von rund 1930
  Jahren ergeben. `Elapsed::parse()` prüft die Form jetzt vorab mit derselben Regex,
  die das Formularfeld als `pattern` trägt. Vom Unit-Test gefunden, nicht vom Auge.
- **Alle Formate mit `!` verankert.** Ohne den Präfix erbt `createFromFormat` die
  nicht genannten Felder von der aktuellen Uhrzeit — `1996-05-15 13:45` hätte die
  Sekunden von *jetzt* übernommen, und dieselbe Konfiguration je nach Renderzeitpunkt
  eine andere Tagesdifferenz ergeben.
- **`Elapsed::since()` bekommt beide Zeitpunkte übergeben.** Kein Zugriff auf die
  Systemuhr, dadurch sind Schaltjahr- und Jahreswechselgrenzen überhaupt testbar
  (29.02.2024 → 28.02.2025 ist noch kein Jahr, → 01.03.2025 ist eines).
- **Testlauf-Wegweiser:** `composer run test` (Host, Fallback), `.docker/test.sh`
  (Container, ICU), `node tests/parity/parity.mjs` (Browser-Seite). Die
  GitHub-Action aus §11 bliebe weiterhin optional, hätte hier aber drei klar
  benannte Kommandos.

---

## Während Slice 3 (Phase 5–7) entschieden & gelernt

- **Die Namespace-Map ist die stille Falle der Symlink-Entwicklung.** Joomla baut
  `administrator/cache/autoload_psr4.php` nur neu, wenn die Datei **fehlt** —
  `extension:discover` rührt sie nicht an. Solange das Modul nicht darin steht, findet
  `ModuleDispatcherFactory` die Dispatcher-Klasse nicht, fällt wortlos auf den
  generischen Dispatcher zurück, und `src/` läuft nie: keine Fehlermeldung, keine
  Log-Zeile, die Seite rendert normal. Aufgefallen erst, weil im Query-Mitschnitt eines
  echten Seitenaufrufs keine einzige `COUNT(*)`-Abfrage auftauchte. `setup.sh` löscht
  die Datei jetzt, `.docker/README.md` nennt den Handgriff für neue Klassen.
- **Nachweis über den MariaDB-Mitschnitt statt über einen headless Bootstrap.** Ein
  `SiteApplication` im CLI hochzuziehen scheitert an Uri und Session-Service — der
  Versuch war eine Sackgasse. Stattdessen `general_log` für genau einen Seitenaufruf
  einschalten: dort stehen die fünf Zähl-Queries der beiden Modulinstanzen wörtlich,
  inklusive Nested-Set-Join und `EXISTS`-Unterabfrage. Das prüft die ganze Kette
  Dispatcher → HelperFactory → Helper → Counter im echten Request.
- **Modul-Cache verifiziert.** Bei globalem Caching an und `cache=1`: drei
  Zähl-Queries beim kalten Aufruf, **null** beim zweiten. Wichtig fürs Testen: mit
  globalem Caching **aus** greift Joomlas Modul-Cache überhaupt nicht, egal was der
  Modulparameter sagt — der Stack steht deshalb bewusst auf „aus".
- **`published_unpublished` ignoriert die Veröffentlichungsdaten.** Das ist so
  spezifiziert (§4.1 nennt Start-/Ablaufdatum nur bei `published`), war aber in meiner
  Sollwert-Tabelle falsch angenommen — das Integrationsskript hat es aufgedeckt.
  Erwartung und Begründung sind korrigiert.
- **Der Doku-Artikel verfälschte die Matrix, die er beschreibt.** Artikel 660 lag in
  Kategorie 601 und wurde damit mitgezählt. Er liegt jetzt in „Uncategorised"; die
  Sollwerte stimmen wieder. Lehre für weitere Fixtures: alles, was der Erklärung dient,
  gehört außerhalb des gezählten Baums.
- **E1, E2, E3 sind umgesetzt** wie vorgeschlagen. E2 kostet einen Join auf
  `#__categories`, der ohnehin für `include_children` gebraucht wird; das
  Integrationsskript prüft beide Richtungen (Kategorie 606 liefert `published` = 0 und
  `any` = 1). Offen für die QA-Checkliste: zwei Benutzer mit verschiedenen
  Zugriffsebenen müssen bei aktivem Access-Filter verschiedene Zahlen sehen — der
  Cache-Schlüssel enthält die View-Levels, geprüft ist das bisher nur durch Lesen.
- **`skip_empty` bindet nicht.** Die Zugriffsebenen in der `EXISTS`-Unterabfrage werden
  als `int` inline gesetzt statt gebunden: die eigenen Parameter einer Unterabfrage
  gehen verloren, sobald sie zu einem String wird und eingebettet wird. Die Werte
  stammen aus `getAuthorisedViewLevels()` und sind Ganzzahlen — im Code kommentiert.

---

## Während Slice 4–5 (Phase 8–10) entschieden & gelernt

- **Die Asset-URI enthält den `css`/`js`-Ordner nicht.** Mit
  `"uri": "mod_dinkymetrics/css/dinkymetrics.css"` lud gar nichts — ohne Fehler,
  ohne Log-Zeile. `HTMLHelper::includeRelativeFiles()` zerlegt den Pfad und setzt
  den Ordner selbst ein, sucht also nach `media/mod_dinkymetrics/css/css/…`.
  Findet es nichts, liefert `WebAssetItem::getUri()` einen leeren String, und der
  `StylesRenderer` überspringt den Eintrag wortlos. Richtig ist
  `"uri": "mod_dinkymetrics/dinkymetrics.css"` — genau so schreibt es der Core
  (`media/com_content/joomla.asset.json` verweist auf
  `com_content/articles-list.min.js`, die Datei liegt in `js/`). **Die Datei liegt
  weiterhin in `css/` bzw. `js/`** — R4 aus der Risikoliste gilt unverändert für
  das Paketieren, nur die URI lässt den Ordner weg.
- **Diagnoseweg für so etwas:** die Kette rückwärts prüfen — Registry-Datei
  registriert? Asset bekannt? Asset aktiv? `getUri()` auflösbar? Der Bruch saß erst
  beim letzten Schritt. Eine temporäre `<!-- DBG … -->`-Zeile im Layout ist dafür
  das schnellste Werkzeug, weil sie im echten Request läuft.
- **Erster Animationsframe konnte negativ werden.** Der Zeitstempel, den ein
  `requestAnimationFrame`-Callback bekommt, ist die Startzeit des Frames und kann
  **vor** dem `performance.now()` liegen, das den Tween-Beginn markiert. Ergebnis
  war ein kurzes Aufblitzen von `-0.7` statt `0.0`. Der Fortschritt wird jetzt an
  beiden Enden geklemmt. Gefunden mit einer kontrollierten Uhr: `rAF` durch eine
  eigene Warteschlange ersetzen und die Callbacks mit selbst gewählten Zeitstempeln
  aufrufen — damit ist der Tween ohne echtes Rendering exakt prüfbar.
- **Das Browser-Panel rendert nicht.** `document.visibilityState` ist dort dauerhaft
  `hidden`, `requestAnimationFrame` feuert null Frames, und
  `prefers-reduced-motion` meldet `reduce`. Für die Animation heißt das: der
  `in-view`-Trigger (IntersectionObserver) lässt sich hier **nicht** prüfen und
  bleibt Handarbeit in der QA-Checkliste (Punkt 5). Zwei Dinge belegt es dafür
  nebenbei: QA-Punkt 7 (reduzierte Bewegung → keine Animation, Endwerte stehen) und
  dass ein Ausfall der Animation immer den korrekten Serverwert stehen lässt.
- **Idempotenz sauber belegt.** Ein zweiter Import des Moduls reiht **null** Frames
  ein. Die zuerst gemessenen sechs stammten von anderen Seitenskripten, die der
  globale `rAF`-Patch mitgefangen hatte — beim Messen mitzuzählen, wer eine Frame
  anfordert, war nötig, um das auseinanderzuhalten.
- **Gemischte Instanzen funktionieren.** Nur Instanzen mit `animate=yes` bekommen
  die `data-trigger`/`data-duration`-Attribute; das JS greift sich
  `.mod-dinkymetrics[data-trigger]`. Verifiziert mit zwei Instanzen auf einer Seite,
  eine mit und eine ohne Animation, bei einem gemeinsamen Skript.
- **Leere Ausgabe im echten Layout bestätigt.** Modul ohne auflösbare Kennzahlen:
  kein Wrapper, keine Card, kein Titel — das Chrome verschwindet mit.
- **`aria-label` folgt dem Spec-Beispiel** (`≈ 1,234.5+`): Präfix als eigenes Wort
  abgesetzt, Suffix direkt an der Zahl, damit ein Prozentzeichen richtig vorgelesen
  wird.

---

## Während Slice 6 (Phase 11–12) entschieden & gelernt

- **Die Excludes im `build.xml` sind der eigentliche Inhalt.** Weil dieses Repo
  Extension-Root *und* Entwicklungsumgebung ist, liegen `composer.json`, `tests/`,
  `vendor/` und die beiden `.dist`-Dateien neben dem Manifest. Sie stehen deshalb
  einzeln in der Ausschlussliste, nicht nur `.*`. Geprüft: das Paket enthält 17
  Dateien, null Treffer auf `composer|phpunit|phpcs|tests/|vendor/`.
- **`<element>` ist bei Modulen der Modulname.** In `update.xml` steht
  `mod_dinkymetrics` mit `<type>module</type>` und `<client>site</client>`; ein
  `<folder>` gibt es nur bei Plugins.
- **Dokumentation nach dem Muster der Schwesterprojekte**: `README.md` für die
  Anwendung (Parameter, Markup-Vertrag, Override, Cache, Barrierefreiheit),
  `.doc/ARCHITECTURE.md` als technische Referenz mit Dateikarte, Datenfluss, den
  fünf Invarianten und der Liste der Fallen. `CHANGELOG.md` steht auf `[1.0.0]`.
- **Zwei Warnhinweise sind ins README gewandert**, weil beide nach einem Fehler
  aussehen und keiner einer ist: dass die Modulzahl von Joomlas
  „Article Count"-Abzeichen abweicht, und dass „Jeder Status" tatsächlich auch den
  Papierkorb mitzählt.

---

## Slice 7 — Abnahme auf Joomla 6.1.3 aus dem Paket

`.docker/setup-j6.sh` zieht eine frische Joomla 6 hoch und installiert **aus dem
gebauten ZIP**, nichts ist symlinkt. Ergebnis: identische Ausgabe wie auf dem
Joomla-5-Stack, gleiche neun Kennzahlen, gleiche Werte, CSS und JS geladen.

| §13 | Prüfpunkt | Ergebnis |
|---|---|---|
| 1 | Installiert und deinstalliert sauber | **ok** — Dateien in `modules/` und `media/` korrekt abgelegt, Sprachdateien bleiben im Extension-Ordner; Deinstallation entfernt Dateien, `#__extensions`-Zeile und beide Modulinstanzen. Die drei verwaisten `#__modules_menu`-Zeilen sind Joomlas eigene (IDs 6, 7, 14) und stehen im J5-Stack genauso, wo nie deinstalliert wurde |
| 2 | Ohne Kennzahlen keine Ausgabe | **ok** — kein Wrapper, keine leere Card |
| 3 | Eine/viele Kennzahlen in Reihenfolge | **ok** — 9 Kennzahlen über zwei Instanzen |
| 4 | Ohne JavaScript alle Endwerte korrekt | **ok** — serverseitig gerendert |
| 5 | `in-view` zählt genau einmal | **ok** — im Browser bestätigt (2026-09-03): die Zähler laufen beim Hineinscrollen hoch |
| 6 | `on-load` zählt direkt | **ok** (Slice 5, kontrollierte Uhr) |
| 7 | `prefers-reduced-motion` | **ok** (Slice 5) |
| 8 | Präfix/Suffix server- wie clientseitig, `aria-label` | **ok** |
| 9 | Verlinkte Kennzahl intern und extern | **ok** — intern über `Route::_()`, extern mit `rel="noopener noreferrer"`, `mailto:` erlaubt, `javascript:` und Nicht-URLs erzeugen **keinen** Link |
| 10 | Zahlenformat folgt `format_locale`; `content_count` zählt Sprache + `*` | **ok** — Zahlenformat: leer → `1,234.5`, `de-DE` → `1.234,5`, `fr-FR` → `1 234,5`. Sprachfilter: gezielt an `Counter::articles()` geprüft statt eine mehrsprachige Instanz aufzusetzen — Kategorie 602, `language=en-GB` → 3 (611, 612 „*", 619; 618 fällt raus), `language=de-DE` → 3 (611, 612, 618; 619 fällt raus). Zwei verschiedene Sollmengen, nicht nur eine andere Zahl — belegt, dass genau die richtigen Artikel je Sprache ein-/ausgeschlossen werden (`tests/Integration/counts.php`) |
| 11 | Modul-Cache | **ok** (Slice 3: kalt 3 Queries, warm 0) |
| 12 | Zwei Instanzen mit verschiedenen Parametern | **ok** |
| 13 | Template-Override greift | **ok** — beide Instanzen nutzen ihn, Originalmarkup verschwindet |
| 14 | RTL | **ok** — Testseite mit dem echten `dinkymetrics.css` unter `dir="rtl"` gerendert (keine echte RTL-Joomla-Installation nötig, da RTL eine reine CSS-Eigenschaft ist): die Flex-Reihe kehrt sich korrekt um (erste Kennzahl steht jetzt rechts, x-Positionen exakt gespiegelt), Präfix/Zahl/Suffix bleiben in der richtigen Lesefolge. Das CSS hat ohnehin nur eine einzige logische Eigenschaft (`margin-block-start`, richtungsunabhängig) und ansonsten nur zentrierte/symmetrische Regeln |
| 15 | Opt-in-CSS abwählbar | **ok**, aber anders als dokumentiert (s. u.) |

### Was die Abnahme korrigiert hat

- **`resolveLink()` ließ Unsinn durch.** `"not a url at all"` wurde zu
  `href="/not a url at all"` geroutet. Relative Links mit rohem Leerzeichen werden
  jetzt verworfen — ein echter Pfad trüge `%20`. Sicherheitsrelevant war es nicht
  (`javascript:` war schon vorher blockiert), kaputt aber schon.
- **Die README-Anleitung zum Abschalten des CSS war falsch.** Sie empfahl
  `disableStyle()` „aus dem Template". Zwei Messungen, sauber wiederholt:
  - **direkt im Template-`index.php`**: die Seite bricht ein — das Asset ist zu dem
    Zeitpunkt noch nicht registriert (das Modul rendert später), der Aufruf wirft,
    und es steht **kein** Modul mehr auf der Seite.
  - **in einem `onBeforeCompileHead`-Listener**, mit `assetExists()` abgesichert:
    funktioniert — CSS weg, JS bleibt, Modul rendert normal.

  Das README beschreibt jetzt beide tragfähigen Wege: Layout-Override ohne
  `useStyle()`, oder ein Systemplugin auf `onBeforeCompileHead`.
- **Zwischenzeitlich falsch gemessen.** Der erste Anlauf zu diesen beiden Fällen lief
  über ein `sed`, das die eingefügte Zeile zerlegte (`$this` ging verloren). Beide
  Ergebnisse waren damit Artefakte des Testaufbaus, nicht Joomlas Verhalten — erst
  ein sauber eingespieltes Patch-Skript brachte die belastbaren Zahlen. Lehre: wenn
  ein Test überraschend „funktioniert nicht" sagt, zuerst den Test prüfen.
- **Joomlas Sprach-Cache gehörte root.** Nach der CLI-Installation warf jeder
  Seitenaufruf `file_put_contents(... /administrator/cache/language/...): Permission
  denied` — Joomla-eigen, nicht unseres, aber es hätte echte Warnungen verdeckt.
  Beide Setup-Skripte übergeben `cache/`, `administrator/cache/` und `tmp/` jetzt an
  www-data.

### Cache-Trennung nach Zugriffsebene — abgeschlossen (2026-09-04)

Letzter offener QA-Punkt aus Slice 7. Ablauf: globales Caching an, Modulparameter
*Caching* auf *Use Global* (beides bereits im Stack gesetzt), abgemeldet zeigt
„Beitraege" (Instanz 900) **8.0**. Nach Anmeldung **im Frontend** als `admin`
(Gruppe *Super Users*, die in Zugriffsebene *Registered* explizit geführt wird)
zeigt dieselbe Kennzahl **9.0** — Artikel 617 zählt jetzt mit. Beide Werte danach
noch einmal wechselseitig gegengeprüft (abgemeldeter `curl`-Aufruf weiterhin 8.0,
angemeldete Browser-Ansicht weiterhin 9.0): zwei getrennte Cache-Einträge, keiner
überschreibt den anderen.

*(Admin- und Site-Sitzung sind bei Joomla getrennt — die frühere
Backend-Anmeldung galt fürs Frontend nicht, dafür war eine zweite, kurze
Anmeldung nötig. Die erste Fassung dieser Anweisung hatte zudem die falsche
Kennzahl genannt: `category_count` wendet die Zugriffsebenen nur auf die
`skip_empty`-Unterabfrage an und ist deshalb für alle Betrachter gleich —
„Beitraege" [`content_count`] ist die einzige Kennzahl in den Fixtures, die
sich mit der Anmeldung ändert.)*

### QA 10 (Mehrsprachigkeit) und QA 14 (RTL) — abgeschlossen ohne eigene Installation

Beides ließ sich präziser und ohne eine zweite Sprache/RTL-Installation
aufzusetzen direkt prüfen:

- **Sprachfilter:** zwei neue Fälle in `tests/Integration/counts.php`, die
  `Counter::articles()` direkt mit `language='en-GB'` bzw. `'de-DE'` aufrufen.
  Kategorie 602, Status „published": mit `en-GB` zählen 611, 612 (Sprache „*")
  und 619 (en-GB), 618 (de-DE) fällt raus → 3; mit `de-DE` genau umgekehrt,
  ebenfalls 3. Zwei **verschiedene** Sollmengen bei gleicher Summe — das belegt,
  dass tatsächlich die richtigen Artikel je Sprache aus- bzw. eingeschlossen
  werden, nicht nur, dass sich irgendeine Zahl ändert. Damit 18/18 Checks statt
  16/16.
- **RTL:** eine winzige statische Testseite mit dem echten, unveränderten
  `dinkymetrics.css` und dem echten Markup, einmal mit `dir="ltr"`, einmal mit
  `dir="rtl"` gerendert. Ergebnis: die Flex-Reihe kehrt sich korrekt um (erste
  Kennzahl steht unter RTL rechts statt links, alle drei Positionen exakt
  gespiegelt gemessen), Präfix/Zahl/Suffix bleiben in der richtigen Lesefolge.
  Nachvollziehbar, weil das CSS ohnehin nur eine einzige logische Eigenschaft
  (`margin-block-start`) sowie zentrierte/symmetrische Regeln enthält — echtes
  Kippen war also gar keine RTL-spezifische Eigenleistung des Moduls, sondern
  eine Bestätigung, dass keine versteckte physische Richtungsangabe
  (`margin-left` o. Ä.) das Zentrieren durchkreuzt.

Damit sind alle Positionen aus §13 der Spezifikation sowie die drei
Nachzügler aus der Abnahme (Slice 7) abgeschlossen.

---

## Custom subform layout (Variante B, nachträglich)

Nach der Abnahme umgesetzt, auf Nutzerwunsch: Variante A (`subform.repeatable`,
Section-Layout statt Tabelle) macht das Formular benutzbar, zeigt pro Zeile aber
weiterhin alle 16 Rohfelder. Variante B ersetzt das durch eine Übersicht — pro
Zeile eine Zusammenfassungszeile (Beschriftung, Quelle, Kurzfassung der
Konfiguration), aufklappbar für die Details. Umfang laut Absprache: Zusammenfassung
mit Beschriftung/Quelle/Kurzfassung, aufklappbar, Drag-and-Drop für die
Reihenfolge (Joomlas Bordmittel).

**Vorher geklärt, weil sonst Fehlinvestition drohte:**

- **Eigener Feldtyp ohne `field/`-Ordner.** Ein Modul kann eigene Feldtypen
  registrieren, ohne der klassischen `field/`-Konvention zu folgen (die einen
  nicht-namespaced `JFormField<Typ>` verlangt): das Manifest trägt
  `addfieldprefix="TheLoom\Module\DinkyMetrics\Site\Field"` auf `<config>`,
  Joomla registriert das (`Form::syncPaths()`, ausgelöst beim Laden des
  Formulars) und sucht für `type="dinkyfigures"` dann nach
  `TheLoom\Module\DinkyMetrics\Site\Field\DinkyfiguresField` — über die
  ohnehin vorhandene PSR-4-Namespace-Map, kein separater Ladepfad nötig.
- **Eigenes Layout ohne Eingriff in `JPATH_ROOT/layouts`.** `FormField::getRenderer()`
  übergibt die Include-Pfade aus `getLayoutPaths()` an den `FileLayout`-Renderer,
  und `FileLayout::sublayout()` reicht dieselben Pfade an Unter-Layouts weiter
  (`$sublayout->includePaths = $this->includePaths`). Ein überschriebenes
  `getLayoutPaths()`, das den eigenen `layouts/`-Ordner des Moduls einschiebt
  (vor dem globalen Fallback, aber hinter einem Admin-Template-Override), reicht
  also für die komplette Layout-Kette. Layout-IDs sind reine Pfad-Segmente
  (Punkt → Slash), keine Joomla-Konvention à la `com_x.y` nötig.
- **Drag-and-Drop ist bereits eingebaut, nichts Eigenes nötig.** Joomlas
  `joomla-field-subform`-Webkomponente verdrahtet den `.group-move`-Button
  bereits mit echtem HTML5-Drag (kein Sortable.js, keine Fremdbibliothek) plus
  Hoch/Runter-Buttons als Fallback — vorausgesetzt, die Buttons tragen exakt
  diese Klassen und der äußere Zeilen-Wrapper trägt `.subform-repeatable-group`
  mit `data-base-name`/`data-group`. Das eigene Layout übernimmt diese Klassen
  unverändert; Hinzufügen/Entfernen/Umsortieren sind dadurch unverändertes
  Core-Verhalten.

**Umsetzung:**

- `src/Field/DinkyfiguresField.php` — dünne `SubformField`-Unterklasse, nur
  `getLayoutPaths()` überschrieben.
- `src/Field/FigureSummary.php` — baut die Kurzfassung serverseitig aus dem
  Zeilen-`Form` (`Form::getValue()`, öffentlich). Enum-Texte (Quelle, Status,
  Einheit, Erweiterung) sind als kleine Label-Tabellen gespiegelt statt aus dem
  Formularfeld reflektiert — `ListField::getOptions()` ist `protected`, und das
  Spiegeln ist dasselbe Muster, das `DinkyMetricsHelper::oneOf()` schon für
  dieselben Werte nutzt. Kategorietitel kommen über eine direkte, gecachte
  DB-Abfrage, nicht aus dem (dashindentierten) Options-Text.
- `layouts/field/subform/dinkymetrics.php` + `.../dinkymetrics/row.php` — Wrapper
  bzw. Zeile. Zeile: `<summary>` mit Zusammenfassung, `<details>` fürs Aufklappen
  (natives HTML, kein ARIA-Aufwand), Move/Remove/Add-Buttons als Geschwister
  außerhalb des `<details>` — reorderbar/löschbar, ohne aufzuklappen.
- `media/mod_dinkymetrics/{css,js}/admin-figures.*` — eigenes Backend-Asset-Paar
  (`mod_dinkymetrics.admin-figures`), unabhängig vom Site-Stylesheet (das bleibt
  farblos/frontend-only; im Backend ist eigenes Styling unproblematisch).
  Das JS hält die Zusammenfassung nach einer Bearbeitung aktuell: ein
  `toggle`-Listener (Capture-Phase, weil das native `toggle`-Event nicht
  bubbelt) liest beim Zuklappen die aktuell sichtbaren Feldwerte aus dem DOM und
  schreibt sie in die Zusammenfassung — bewusst nur bei Zuklappen, nicht bei
  jedem Tastendruck, und bewusst nur Beschriftung/Quelle/Kategorie (die drei
  Angaben, die eine Zusammenfassung am stärksten prägen); die volle Kurzfassung
  mit allen Filtern ist nach dem nächsten Speichern wieder exakt.

**Verifiziert:** `phpcs` sauber, alle 72 Unit-Tests, alle 16 Zähl-Checks und die
Formatierungsparität weiterhin grün — nichts an der Auflösungslogik ist berührt.
Speicher-Roundtrip geprüft (sechs Zeilen, JSON vor/nach identisch). Neue Zeile
startet aufgeklappt, bestehende eingeklappt; Zuklappen nach Bearbeitung
aktualisiert die Zusammenfassung live. Auf Joomla 6.1.3 aus dem gebauten ZIP
installiert (Upgrade über die bestehende Installation) — identisches Verhalten,
keine Konsolenfehler, `layouts/` und die neuen `media/`-Dateien landen kopiert
(nicht symlinkt) exakt dort, wo das Manifest sie hinschickt.

**Aus Sicherheitsgründen nicht selbst behoben:** ein `TypeError` beim ersten
Testlauf war ein Fehler in meinem eigenen Spike-Code (`_JEXEC`-Wächterzeile vor
der `namespace`-Deklaration — PHP verbietet das kategorisch); danach eine 500 im
Browser, ebenfalls Folge desselben Tippfehlers, behoben mit derselben Korrektur.

**Offen für später:** die JS-Kurzfassung bildet nur `content_count`/
`category_count`s Kategorie ab, nicht Status/Featured/`skip_empty` — bewusst
knapp gehalten (s. o.); bei Bedarf ließe sich das erweitern, sobald klar ist, ob
die volle Detailtreue während der Bearbeitung tatsächlich gebraucht wird.

### Nachbesserung: Modal statt Aufklappen (auf Nutzerfeedback)

Die `<details>`-Variante wirkte pro Zeile überladen — Plus/Minus/Move/Hoch/Runter
nebeneinander, nur um drei Kennzahlen zu verwalten. Umgebaut auf Wunsch: jede
Zeile jetzt nur noch **Drag-Griff · Zusammenfassung · Stift · Mülleimer**;
Bearbeitung öffnet ein Bootstrap-Modal mit dem vollständigen Formular. Der
globale „+"-Button in der Werkzeugleiste bleibt (einzige Stelle zum Hinzufügen);
pro Zeile gibt es kein eigenes Plus mehr, und die Hoch/Runter-Pfeile sind
komplett entfallen — nur noch Ziehen per Maus.

**Barrierefreiheits-Kompromiss, bewusst eingegangen:** ohne Hoch/Runter-Buttons
gibt es keinen tastatur- oder screenreader-bedienbaren Weg mehr, eine Zeile
umzusortieren — reines Maus-Drag ist für diese Nutzergruppen nicht erreichbar.
Bei typischerweise drei Kennzahlen ist der Verlust in der Praxis gering
(Löschen + Neuanlegen in der gewünschten Reihenfolge ist ein Umweg, aber
machbar), sollte aber nicht stillschweigend untergehen.

**Vorher geklärt, weil es exakt in die Kalender-Feld-Falle aus Slice 1 gelaufen
wäre:** `joomla-field-subform.js` benennt beim Klonen einer neuen Zeile nur
Elemente mit einem `name`-Attribut um (`fixUniqueAttributes()` läuft über
`row.querySelectorAll('[name]')`). Eine selbst vergebene Modal-`id` (kein
`name`) bliebe für jede neue Zeile wortwörtlich `"figuresX"` — mit zwei neuen
Zeilen zwei Modals mit identischer ID, `data-bs-target` träfe immer nur das
erste. Verifiziert (nicht nur gelesen): zwei Zeilen hintereinander per „+"
angelegt, beide IDs eindeutig (`dm-modal-figures6`, `dm-modal-figures7`).

Die Lösung braucht keine eigene Umbenennungslogik: die Webkomponente feuert
nach dem Klonen ein `subform-row-add`-Event (bubbelnd) mit der fertig
umbenannten Zeile im Detail — zu dem Zeitpunkt trägt `row.dataset.group`
bereits den echten, eindeutigen Namen. `admin-figures.js` hört genau darauf,
kopiert den Namen in die Modal-`id` und den `data-bs-target` der Zeile und
öffnet das Modal direkt (`trigger.click()`) — die neue, leere Zeile geht damit
sofort in die Bearbeitung statt eine leere Zusammenfassung zu zeigen.

Zwei weitere Bootstrap-Mechanismen mussten vorher stimmen:

- Bootstraps `[data-bs-toggle="modal"]`-Aktivierung ist **delegiert auf
  `document`** (`EventHandler.on(document, 'click.bs.modal.data-api',
  '[data-bs-toggle="modal"]', …)`), nicht pro Element beim Laden verdrahtet —
  funktioniert deshalb automatisch auch für Zeilen, die erst nach dem
  Seitenaufbau hinzukommen.
- Bootstraps eigene Events (`hidden.bs.modal` etc.) bubbeln standardmäßig
  (`EventHandler.trigger()` setzt `bubbles: true`, sofern kein jQuery
  eingreift) — ein einziger `document`-Listener für die
  Zusammenfassungs-Aktualisierung reicht für alle Zeilen, auch neu
  hinzugefügte.

Bootstraps Modal-JS (`bootstrap.modal`) ist als eigenständiges Web-Asset
registriert und wird jetzt explizit geladen (`$wa->useScript('bootstrap.modal')`),
statt sich darauf zu verlassen, dass es durch Zufall schon da ist.

**Verifiziert:** Modal öffnet mit allen Feldern, Bearbeiten + Schließen
aktualisiert die Zusammenfassung, zwei neu angelegte Zeilen bekommen
eindeutige IDs und öffnen sich beide automatisch, Drag-Handle löst weiterhin
`draggable="true"` beim Gedrückthalten aus (unverändertes Core-Verhalten),
Löschen funktioniert. Speicher-Roundtrip erneut geprüft — inklusive einer
Bearbeitung durch das Modal hindurch —, JSON vor/nach identisch. Auf Joomla
6.1.3 aus dem neu gebauten Paket per Upgrade-Installation getestet: gleiches
Verhalten, keine Konsolenfehler. Volle Regression (`phpcs`, 72 Unit-Tests, 16
Zähl-Checks, Formatierungsparität) unverändert grün.

### Nachbesserung: Feinschliff auf Nutzerfeedback (Rahmen, Kontrast, Modal-Padding, Hoch/Runter)

Vier gemeldete Punkte, alle auf denselben zwei Ursachen zurückgeführt statt
einzeln kuriert.

**Ursache 1 — geratene CSS-Variablen statt Bootstraps eigener Tokens.**
`var(--component-bg, #fff)` sollte eine dezente Kartenfarbe liefern; Atum 6
definiert `--component-bg` gar nicht, der Fallback `#fff` griff also **immer**
und malte im Dark-Theme eine weiße Box. Die Beschriftung („Jahre") hatte keine
eigene Textfarbe und erbte die helle Standardschrift des Dark-Themes — weißer
Text auf weißer Fläche, unsichtbar. Was wie „die Position der Zusammenfassung
wirkt zufällig" aussah, war genau das: der sichtbare Text begann einfach
hinter einem unsichtbaren Wort.

Nachgesehen statt weiter geraten: Atum 6 definiert unter `[data-bs-theme=dark]`
eigene, **unpräfixierte** Bootstrap-5.3-Variablen (`--border-color`,
`--body-color`, `--secondary-color`, `--tertiary-bg` …) und daneben, nur fürs
**Light**-Theme, die klassischen Sass-Namen (`--dark`, `--gray-600`, `--primary`
…) — letztere werden fürs Dark-Theme **nicht** neu gesetzt und behalten dort
ihren hellen … nein, dunklen Light-Mode-Wert bei, was zufällig lesbar blieb,
aber nicht dem Grundproblem half. Behoben durch Umstieg auf Bootstraps eigene,
theme-fähige **Utility-Klassen** im Markup (`bg-body-tertiary`,
`text-body-secondary`, `border`) statt eigener `var()`-Deklarationen — Atums
Chrome besteht selbst aus genau diesen Klassen, sie sind also garantiert
korrekt an beide Themes gebunden. Für die Hover-Zustände der Buttons ein
neutrales, theme-unabhängiges `rgba(127,127,127,.2)` statt einer weiteren
Variable.

**Ursache 2 — der doppelte Rahmen kam tatsächlich von Joomla.** Der äußere
`.subform-repeatable-group`-Container trägt in Atum eigenes Padding
(`32px 32px 16px 28px`) und einen eigenen Rahmen — zugeschnitten auf das
ursprüngliche mehrzeilige Repeatable-Layout, nicht auf eine einzeilige
Zusammenfassung. Mit
`.dinkymetrics-figures .subform-repeatable-group { padding:0; border:0;
background:transparent; }` verschwindet die äußere Box vollständig; übrig
bleibt nur die eine, selbst gestaltete Zeile.

**Modal-Padding — zwei generische Atum-Regeln, für Iframe-Inhalte gedacht.**
`.modal-dialog .modal-body{padding:0}` und `.modal-header{padding:0 15px}`
gelten unbedingt für **jedes** Bootstrap-Modal im Joomla-Admin — offenbar in
der Annahme, der Inhalt sei meist ein Iframe (Medien-Manager u. Ä.), das kein
zusätzliches Innenpolster braucht. Jedes Modal mit echtem Formularinhalt muss
sein Padding selbst mitbringen; ein zusammengesetzter Selektor
(`.modal.dinkymetrics-figure__modal .modal-header/-body/-footer`) übersteuert
das zuverlässig unabhängig von der Ladereihenfolge der Stylesheets.

**Zweite, damit zusammenhängende Modal-Falle:** Joomlas
`.control-group`-Feldlayout steht standardmäßig **nebeneinander**
(Label/Feld als Flex-Row) und wird erst durch die Klasse `form-vertical` auf
einem Vorfahren zu einem **gestapelten** Layout
(`.form-vertical .control-group{flex-direction:column}`). Das umgebende
Bearbeitungsformular trägt diese Klasse, ein neu eingefügtes Modal erbt sie
nicht. Ohne sie standen Label und Eingabefeld nebeneinander und sprengten bei
schmalem Modal (`modal-lg` greift erst ab 992px Viewportbreite; darunter
bestimmt Bootstraps Fallback-Breite die Dialogbreite) die verfügbare Breite —
das Eingabefeld landete sichtbar außerhalb des Modals. Ein `form-vertical`
auf dem `.modal-body`-Wrapper behebt es, mit demselben Verhalten wie im Rest
der Admin-Oberfläche.

**Hoch/Runter-Buttons: erst zurückgebaut, dann selbst nachimplementiert, dann
wieder verworfen — weil die erste Schlussfolgerung falsch war.** Ein
erschöpfender Grep über die komplette `joomla-field-subform.js` (608 Zeilen)
nach den Literalen `"group-move-up"`/`"group-move-down"`/`"moveUp"` lieferte
**keinen** Treffer — daraus fälschlich geschlossen, die Buttons aus Joomlas
eigenem `section.php` seien seit einem JS-Rewrite funktionslose Alt-Markup.
Selbst eine Klick-Weiterleitung geschrieben (`admin-figures.js`, DOM-Swap
via `insertBefore`, exakt nach dem Vorbild von `setUpDragSort()`), verifiziert,
dass sie einzeln funktioniert — und erst bei genauerem Hinsehen bemerkt, dass
ein Klick die Zeile **zwei** Positionen statt einer verschob. Ursache: die
Selektoren werden in `joomla-field-subform.js` **zur Laufzeit
zusammengesetzt** (`` `${buttonMove}-up` ``, `` `${buttonMove}-down` `` in
`setUpDragSort()`, ganz am Ende der Datei) statt als Literal-String zu
erscheinen — mein Grep konnte sie deshalb nicht finden, obwohl die Verdrahtung
längst da war. Meine eigene Logik lief parallel zu Joomlas eigener und
verdoppelte jede Bewegung. Die eigene Implementierung wieder entfernt;
Joomlas native Verdrahtung übernimmt Hoch/Runter vollständig, inklusive eines
sinnvollen Wrap-Arounds an den Rändern (erste Zeile hoch → wird letzte, und
umgekehrt), verifiziert durch wiederholtes Klicken und Prüfen der
resultierenden Reihenfolge. **Lehre:** ein Grep, der nichts findet, beweist
bei dynamisch zusammengesetzten Selektoren gar nichts — ausprobieren schlägt
Lesen, sobald beides möglich ist.

**Verifiziert:** Beschriftung, Quelle und Kurzfassung jetzt in beiden Themes
lesbar; nur noch ein Rahmen pro Zeile; Modal-Felder mit Innenabstand und
gestapeltem Label/Feld-Layout; Hoch/Runter bewegt exakt eine Position (bzw.
wrapt an den Rändern), Speicher-Roundtrip über mehrere Zwischenschritte
(Verschieben, Zurückverschieben, Speichern) liefert identisches JSON. Volle
Regression grün, erneut auf Joomla 6.1.3 per Upgrade-Installation aus dem
frisch gebauten Paket bestätigt.

---

## v1.1+ (nicht jetzt)

Weitere Quellen über `resolve()` (`#__users`, `#__contact_details`, Tags,
`#__finder`-Treffer); Kennzahl-Icons; `layout`-Varianten (Grid statt Reihe);
Sprachschlüssel-Unterstützung im `label` für mehrsprachige Sites; GitHub-Action für
`phpcs` / `phpunit` / Build; weitere Sprachpakete als PRs.
