#!/usr/bin/env node
/**
 * @package     TheLoom.Module
 * @subpackage  Site.DinkyMetrics
 *
 * @copyright   Copyright (C) 2026 The Loom / Stefan Schulz. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://www.the-loom.de
 *
 * The browser half of the formatting-parity check.
 *
 * cases.json records what Intl.NumberFormat produced for a set of inputs. This asserts
 * it still does — a newer ICU in the runtime would be caught here rather than as a
 * number that visibly jumps when the count-up animation finishes. The PHP half lives in
 * tests/Unit/FormatterTest.php and reads the same file.
 *
 * Run:  node tests/parity/parity.mjs
 */

import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const cases = JSON.parse(readFileSync(join(here, 'cases.json'), 'utf8'));

/**
 * Format exactly the way media/mod_dinkymetrics/js/dinkymetrics.js has to.
 * Keep the two in step: this is the reference implementation of that one line.
 */
const format = (value, locale, decimals, grouping) =>
  new Intl.NumberFormat(locale, {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
    useGrouping: grouping,
  }).format(value);

const codePoints = (s) =>
  [...s].map((c) => 'U+' + c.codePointAt(0).toString(16).toUpperCase().padStart(4, '0')).join(' ');

let failed = 0;

for (const { value, locale, decimals, grouping, expected } of cases) {
  const actual = format(value, locale, decimals, grouping);

  if (actual !== expected) {
    failed++;
    console.error(
      `MISMATCH  ${value} ${locale} ${decimals}d ${grouping ? 'grouped' : 'plain'}\n` +
        `  expected ${JSON.stringify(expected)}  ${codePoints(expected)}\n` +
        `  actual   ${JSON.stringify(actual)}  ${codePoints(actual)}`
    );
  }
}

console.log(
  `${cases.length - failed}/${cases.length} parity cases match  (node ${process.version}, ICU ${process.versions.icu})`
);

if (failed > 0) {
  console.error(
    `\n${failed} case(s) drifted. Either this runtime's ICU differs from the one that ` +
      `generated cases.json, or the reference formatting changed. Do not simply ` +
      `regenerate the file: the PHP side has to agree with it too.`
  );
  process.exit(1);
}
