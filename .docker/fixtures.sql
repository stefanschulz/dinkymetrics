-- DinkyMetrics test fixtures. Idempotent: safe to run repeatedly.
-- Loaded by setup.sh after the Joomla install + module discovery.
--
-- The whole point of this file is the counting matrix: every article below exists
-- to make exactly one filter of content_count / category_count observable. The
-- expected results are listed at the bottom and are the target values of the QA
-- checklist (spec section 13).

SET NAMES utf8mb4;
SET @now    = NOW();
SET @past   = DATE_SUB(@now, INTERVAL 30 DAY);
SET @future = DATE_ADD(@now, INTERVAL 30 DAY);
SET @admin  = (SELECT MIN(id) FROM jos_users);

-- Nested-set anchor: the new subtree is appended inside the category root (id 1),
-- starting at its current right bound. The root's own bound is repaired at the end,
-- which also makes a re-run harmless.
SET @r = (SELECT rgt FROM jos_categories WHERE id = 1);

-- ---------------------------------------------------------------------------
-- Categories
--
--   601 metrics-root                     published
--   ├── 602 child-a                      published
--   │   └── 603 grand-a1                 published
--   ├── 604 child-b                      published
--   ├── 605 metrics-empty                published, holds no articles
--   └── 606 metrics-unpub                UNPUBLISHED, holds one published article
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO jos_categories
(id, asset_id, parent_id, lft, rgt, level, path, extension, title, alias, note, description,
 published, checked_out, checked_out_time, access, params, metadesc, metakey, metadata,
 created_user_id, created_time, modified_user_id, modified_time, hits, language, version)
VALUES
(601, 0, 1, @r + 0, @r + 11, 1, 'metrics-root', 'com_content',
 'Metrics Root', 'metrics-root', '', '', 1, NULL, NULL, 1, '{}', '', '', '{}',
 @admin, @now, NULL, NULL, 0, '*', 1),

(602, 0, 601, @r + 1, @r + 4, 2, 'metrics-root/child-a', 'com_content',
 'Child A', 'child-a', '', '', 1, NULL, NULL, 1, '{}', '', '', '{}',
 @admin, @now, NULL, NULL, 0, '*', 1),

(603, 0, 602, @r + 2, @r + 3, 3, 'metrics-root/child-a/grand-a1', 'com_content',
 'Grandchild A1', 'grand-a1', '', '', 1, NULL, NULL, 1, '{}', '', '', '{}',
 @admin, @now, NULL, NULL, 0, '*', 1),

(604, 0, 601, @r + 5, @r + 6, 2, 'metrics-root/child-b', 'com_content',
 'Child B', 'child-b', '', '', 1, NULL, NULL, 1, '{}', '', '', '{}',
 @admin, @now, NULL, NULL, 0, '*', 1),

(605, 0, 601, @r + 7, @r + 8, 2, 'metrics-root/metrics-empty', 'com_content',
 'Empty Category', 'metrics-empty', '', '', 1, NULL, NULL, 1, '{}', '', '', '{}',
 @admin, @now, NULL, NULL, 0, '*', 1),

(606, 0, 601, @r + 9, @r + 10, 2, 'metrics-root/metrics-unpub', 'com_content',
 'Unpublished Category', 'metrics-unpub', '', '', 0, NULL, NULL, 1, '{}', '', '', '{}',
 @admin, @now, NULL, NULL, 0, '*', 1);

-- Repair the root's right bound (self-correcting, safe to re-run).
UPDATE jos_categories
SET rgt = (SELECT t.mx FROM (SELECT MAX(rgt) AS mx FROM jos_categories WHERE id <> 1) t) + 1
WHERE id = 1;

-- ---------------------------------------------------------------------------
-- Articles — one row per filter that has to be observable
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO jos_content
(id, asset_id, title, alias, introtext, `fulltext`, state, catid, created, created_by,
 created_by_alias, modified, modified_by, publish_up, publish_down, images, urls, attribs,
 version, ordering, metakey, metadesc, access, hits, metadata, featured, language, note)
VALUES
-- in 601 itself: proves the chosen category's own articles are counted
(651, 0, 'Root Article', 'dm-root', '<p>In the root category.</p>', '', 1, 601, @now, @admin,
 '', @now, 0, @past, NULL, '{}', '{}', '{}', 1, 1, '', '', 1, 0, '{}', 0, '*', 'counts'),

-- in 602: the full status / access / language matrix
(611, 0, 'A Plain', 'dm-a-plain', '<p>Published, public, all languages.</p>', '', 1, 602, @now, @admin,
 '', @now, 0, @past, NULL, '{}', '{}', '{}', 1, 1, '', '', 1, 0, '{}', 0, '*', 'counts'),
(612, 0, 'A Featured', 'dm-a-featured', '<p>Published and featured.</p>', '', 1, 602, @now, @admin,
 '', @now, 0, @past, NULL, '{}', '{}', '{}', 1, 2, '', '', 1, 0, '{}', 1, '*', 'counts, featured'),
(613, 0, 'A Unpublished', 'dm-a-unpub', '<p>Unpublished.</p>', '', 0, 602, @now, @admin,
 '', @now, 0, @past, NULL, '{}', '{}', '{}', 1, 3, '', '', 1, 0, '{}', 0, '*', 'state=0'),
(614, 0, 'A Trashed', 'dm-a-trashed', '<p>Trashed.</p>', '', -2, 602, @now, @admin,
 '', @now, 0, @past, NULL, '{}', '{}', '{}', 1, 4, '', '', 1, 0, '{}', 0, '*', 'state=-2'),
(615, 0, 'A Not Yet', 'dm-a-future', '<p>Publishing starts in the future.</p>', '', 1, 602, @now, @admin,
 '', @now, 0, @future, NULL, '{}', '{}', '{}', 1, 5, '', '', 1, 0, '{}', 0, '*', 'publish_up in the future'),
(616, 0, 'A Expired', 'dm-a-expired', '<p>Publishing finished in the past.</p>', '', 1, 602, @now, @admin,
 '', @now, 0, @past, @past, '{}', '{}', '{}', 1, 6, '', '', 1, 0, '{}', 0, '*', 'publish_down in the past'),
(617, 0, 'A Registered', 'dm-a-registered', '<p>Registered access level only.</p>', '', 1, 602, @now, @admin,
 '', @now, 0, @past, NULL, '{}', '{}', '{}', 1, 7, '', '', 2, 0, '{}', 0, '*', 'access=2'),
(618, 0, 'A German', 'dm-a-de', '<p>German only.</p>', '', 1, 602, @now, @admin,
 '', @now, 0, @past, NULL, '{}', '{}', '{}', 1, 8, '', '', 1, 0, '{}', 0, 'de-DE', 'language de-DE'),
(619, 0, 'A English', 'dm-a-en', '<p>English only.</p>', '', 1, 602, @now, @admin,
 '', @now, 0, @past, NULL, '{}', '{}', '{}', 1, 9, '', '', 1, 0, '{}', 0, 'en-GB', 'language en-GB'),

-- in 603: proves include_children reaches the grandchild level
(621, 0, 'A1 Plain', 'dm-a1-plain', '<p>Two levels down.</p>', '', 1, 603, @now, @admin,
 '', @now, 0, @past, NULL, '{}', '{}', '{}', 1, 1, '', '', 1, 0, '{}', 0, '*', 'counts (depth 2)'),
(622, 0, 'A1 Featured', 'dm-a1-featured', '<p>Two levels down, featured.</p>', '', 1, 603, @now, @admin,
 '', @now, 0, @past, NULL, '{}', '{}', '{}', 1, 2, '', '', 1, 0, '{}', 1, '*', 'counts, featured'),

-- in 604: a sibling branch, so skip_empty has something to keep
(631, 0, 'B Plain', 'dm-b-plain', '<p>Sibling branch.</p>', '', 1, 604, @now, @admin,
 '', @now, 0, @past, NULL, '{}', '{}', '{}', 1, 1, '', '', 1, 0, '{}', 0, '*', 'counts'),
(632, 0, 'B Unpublished', 'dm-b-unpub', '<p>Unpublished sibling.</p>', '', 0, 604, @now, @admin,
 '', @now, 0, @past, NULL, '{}', '{}', '{}', 1, 2, '', '', 1, 0, '{}', 0, '*', 'state=0'),

-- in 606: a published article inside an UNPUBLISHED category (decision E2)
(641, 0, 'Hidden Branch', 'dm-hidden', '<p>Published article in an unpublished category.</p>', '', 1, 606, @now, @admin,
 '', @now, 0, @past, NULL, '{}', '{}', '{}', 1, 1, '', '', 1, 0, '{}', 0, '*', 'category unpublished');

-- Keep the featured flag consistent with com_content's own table.
INSERT IGNORE INTO jos_content_frontpage (content_id, ordering, featured_up, featured_down)
VALUES (612, 1, NULL, NULL), (622, 2, NULL, NULL);

-- ---------------------------------------------------------------------------
-- Two module instances, so QA point 12 (two instances, different parameters)
-- has something to look at. Both sit in Cassiopeia's right sidebar.
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO jos_modules
(id, asset_id, title, note, content, ordering, position, checked_out, checked_out_time,
 publish_up, publish_down, published, module, access, showtitle, params, client_id, language)
VALUES
(900, 0, 'DinkyMetrics — all sources', 'in-view, grouped', '', 1, 'sidebar-right',
 NULL, NULL, NULL, NULL, 1, 'mod_dinkymetrics', 1, 1,
 '{"figures":{"figures0":{"label":"Jahre","source":"years_since","date":"1996-05-15","unit":"years","prefix":"","suffix":"","link":""},"figures1":{"label":"Beitraege","source":"content_count","category":"601","include_children":"yes","state":"published","featured":"any","prefix":"","suffix":"","link":""},"figures2":{"label":"Hervorgehoben","source":"content_count","category":"601","include_children":"yes","state":"published","featured":"only","prefix":"","suffix":"","link":""},"figures3":{"label":"Kategorien","source":"category_count","extension":"com_content","parent":"601","include_children_cat":"no","skip_empty":"no","state":"published","prefix":"","suffix":"","link":""},"figures4":{"label":"Feste Zahl","source":"literal","value":"1234.5","prefix":"\\u2248","suffix":"+","link":""},"figures5":{"label":"Verlinkt","source":"literal","value":"42","prefix":"","suffix":"","link":"index.php?option=com_content&view=featured"}},"animate":"yes","animate_trigger":"in-view","animate_threshold":"0.35","animate_duration":"1600","animate_easing":"ease-out","animate_from":"0","respect_reduced_motion":"yes","format_grouping":"yes","format_decimals":"1","format_locale":"","count_all_access":"no","debug":"1","layout":"_:default","moduleclass_sfx":"","cache":"0","cache_time":"900","cachemode":"id","module_tag":"div","bootstrap_size":"0","header_tag":"h3","header_class":"","style":"0"}',
 0, '*'),

(901, 0, 'DinkyMetrics — second instance', 'on-load, ungrouped', '', 2, 'sidebar-right',
 NULL, NULL, NULL, NULL, 1, 'mod_dinkymetrics', 1, 1,
 '{"figures":{"figures0":{"label":"Alle Kategorien tief","source":"category_count","extension":"com_content","parent":"601","include_children_cat":"yes","skip_empty":"no","state":"published","prefix":"","suffix":"","link":""},"figures1":{"label":"Nicht leere Kategorien","source":"category_count","extension":"com_content","parent":"601","include_children_cat":"no","skip_empty":"yes","state":"published","prefix":"","suffix":"","link":""},"figures2":{"label":"Tage seit","source":"years_since","date":"2026-01-01","unit":"days","prefix":"","suffix":"","link":""}},"animate":"yes","animate_trigger":"on-load","animate_threshold":"0.35","animate_duration":"600","animate_easing":"linear","animate_from":"0","respect_reduced_motion":"yes","format_grouping":"no","format_decimals":"0","format_locale":"en-GB","count_all_access":"no","debug":"1","layout":"_:default","moduleclass_sfx":"","cache":"0","cache_time":"900","cachemode":"id","module_tag":"div","bootstrap_size":"0","header_tag":"h3","header_class":"","style":"0"}',
 0, '*');

INSERT IGNORE INTO jos_modules_menu (moduleid, menuid) VALUES (900, 0), (901, 0);

-- ---------------------------------------------------------------------------
-- Expected values (guest, monolingual site, count_all_access = no)
--
--   content_count  cat 601, include_children=yes, published, featured=any
--       -> 8   with decision E2 (articles in unpublished categories are skipped)
--          9   without E2 — article 641 in category 606 would count
--       made up of: 651 (601) + 611,612,618,619 (602) + 621,622 (603) + 631 (604);
--       617 drops out on access, 613/614 on status, 615/616 on the publishing dates
--   content_count  same, featured=only            -> 2   (612, 622)
--   content_count  same, featured=exclude         -> 6
--   content_count  same, count_all_access=yes     -> 9   (617 joins in)
--   content_count  cat 602, include_children=no   -> 4
--   content_count  cat 602, state=any             -> 8   (all nine in 602 except 617 on access)
--   content_count  cat 602, published_unpublished -> 7   (state 0 or 1 only; the publishing
--                                                         dates are not applied for this
--                                                         status, so 615 and 616 count)
--   content_count  cat 606, published             -> 0   (E2: the category is unpublished)
--   content_count  cat 606, state=any             -> 1
--
--   category_count parent 601, all levels, any status            -> 5   (606 joins in)
--   category_count parent 602, direct children, published        -> 1   (603)
--
--   category_count parent 601, direct children, published        -> 3   (602, 604, 605)
--   category_count parent 601, direct children, skip_empty=yes   -> 2   (605 drops out)
--   category_count parent 601, all levels, published             -> 4   (602, 603, 604, 605)
--
--   years_since    1996-05-15, unit=years   -> whole years since that date
-- ---------------------------------------------------------------------------

-- ---------------------------------------------------------------------------
-- Front-end access to the fixture content: three main-menu items, so the
-- counting matrix can be checked by eye against what the site actually shows.
-- ---------------------------------------------------------------------------

SET @cc = (SELECT extension_id FROM jos_extensions WHERE element = 'com_content' AND type = 'component' LIMIT 1);
SET @m  = (SELECT rgt FROM jos_menu WHERE id = 1);

INSERT IGNORE INTO jos_content
(id, asset_id, title, alias, introtext, `fulltext`, state, catid, created, created_by,
 created_by_alias, modified, modified_by, publish_up, publish_down, images, urls, attribs,
 version, ordering, metakey, metadesc, access, hits, metadata, featured, language, note)
VALUES
(660, 0, 'DinkyMetrics — Testdaten und Sollwerte', 'dm-testdaten',
'<p>Diese Seite beschreibt die Testdaten, gegen die <strong>mod_dinkymetrics</strong> gezaehlt wird. Die Sollwerte gelten fuer einen <em>nicht angemeldeten</em> Besucher auf dieser einsprachigen Testinstallation.</p>
<h3>Kategoriebaum</h3>
<ul>
<li><strong>Metrics Root</strong> (601) &mdash; 1 Beitrag
<ul>
<li><strong>Child A</strong> (602) &mdash; 9 Beitraege, davon 4 zaehlbar
<ul><li><strong>Grandchild A1</strong> (603) &mdash; 2 Beitraege, beide zaehlbar</li></ul></li>
<li><strong>Child B</strong> (604) &mdash; 2 Beitraege, davon 1 zaehlbar</li>
<li><strong>Empty Category</strong> (605) &mdash; keine Beitraege</li>
<li><strong>Unpublished Category</strong> (606) &mdash; unveroeffentlicht, enthaelt 1 veroeffentlichten Beitrag</li>
</ul></li>
</ul>
<h3>Warum einzelne Beitraege nicht zaehlen</h3>
<table class="table table-striped"><thead><tr><th>Beitrag</th><th>Grund</th></tr></thead><tbody>
<tr><td>A Unpublished</td><td>Status unveroeffentlicht</td></tr>
<tr><td>A Trashed</td><td>im Papierkorb</td></tr>
<tr><td>A Not Yet</td><td>Startdatum liegt in der Zukunft</td></tr>
<tr><td>A Expired</td><td>Ablaufdatum liegt in der Vergangenheit</td></tr>
<tr><td>A Registered</td><td>Zugriffsebene Registered &mdash; zaehlt nur bei <em>Gesperrte Inhalte mitzaehlen = Ja</em></td></tr>
<tr><td>Hidden Branch</td><td>liegt in einer unveroeffentlichten Kategorie (Entscheidung E2)</td></tr>
</tbody></table>
<h3>Sollwerte</h3>
<table class="table table-striped"><thead><tr><th>Kennzahl</th><th>Soll</th></tr></thead><tbody>
<tr><td>Beitraege unter 601, mit Unterkategorien</td><td><strong>8</strong></td></tr>
<tr><td>&hellip; nur hervorgehobene</td><td><strong>2</strong></td></tr>
<tr><td>&hellip; ohne hervorgehobene</td><td><strong>6</strong></td></tr>
<tr><td>&hellip; mit <em>Gesperrte Inhalte mitzaehlen = Ja</em></td><td><strong>9</strong></td></tr>
<tr><td>Beitraege in 602 ohne Unterkategorien</td><td><strong>4</strong></td></tr>
<tr><td>Kategorien direkt unter 601</td><td><strong>3</strong></td></tr>
<tr><td>&hellip; ohne leere</td><td><strong>2</strong></td></tr>
<tr><td>Kategorien unter 601, alle Ebenen</td><td><strong>4</strong></td></tr>
</tbody></table>
<p><em>Stand der Umsetzung:</em> Das Modul ist installiert und vollstaendig konfigurierbar, gibt aber noch nichts aus &mdash; die Aufloesung der Kennzahlen und das Markup kommen in den naechsten Bauabschnitten. In der rechten Spalte stehen bereits zwei Modul-Instanzen; sie erscheinen dort, sobald sie Werte liefern.</p>',
'', 1, 2, @now, @admin, '', @now, 0, @past, NULL, '{}', '{}', '{}', 1, 2, '',
'Testdaten und erwartete Zaehlwerte fuer mod_dinkymetrics.', 1, 0, '{}', 0, '*', 'documentation');

INSERT IGNORE INTO jos_menu
(id, menutype, title, alias, note, path, link, type, published, parent_id, level, component_id,
 checked_out, checked_out_time, browserNav, access, img, template_style_id, params, lft, rgt,
 home, language, client_id, publish_up, publish_down)
VALUES
(710, 'mainmenu', 'Testdaten & Sollwerte', 'dm-testdaten', '', 'dm-testdaten',
 'index.php?option=com_content&view=article&id=660', 'component', 1, 1, 1, @cc,
 NULL, NULL, 0, 1, '', 0, '{"show_page_heading":1}', @m + 0, @m + 1, 0, '*', 0, NULL, NULL),

(711, 'mainmenu', 'Kategorien mit Beitragszahlen', 'dm-kategorien', '', 'dm-kategorien',
 'index.php?option=com_content&view=categories&id=601', 'component', 1, 1, 1, @cc,
 NULL, NULL, 0, 1, '', 0,
 '{"show_base_description":"1","maxLevelcat":"3","show_empty_categories_cat":"1","show_subcat_desc_cat":"1","show_cat_num_articles_cat":"1","show_page_heading":1}',
 @m + 2, @m + 3, 0, '*', 0, NULL, NULL),

(712, 'mainmenu', 'Beiträge in Child A', 'dm-child-a', '', 'dm-child-a',
 'index.php?option=com_content&view=category&layout=blog&id=602', 'component', 1, 1, 1, @cc,
 NULL, NULL, 0, 1, '', 0,
 '{"num_leading_articles":"0","num_intro_articles":"10","num_columns":"1","num_links":"0","show_page_heading":1}',
 @m + 4, @m + 5, 0, '*', 0, NULL, NULL);

-- Repair the menu root's right bound (self-correcting, safe to re-run).
UPDATE jos_menu
SET rgt = (SELECT t.mx FROM (SELECT MAX(rgt) AS mx FROM jos_menu WHERE id <> 1) t) + 1
WHERE id = 1;
