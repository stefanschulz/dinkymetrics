<?php

/**
 * @package     TheLoom.Module
 * @subpackage  Site.DinkyMetrics
 *
 * @copyright   Copyright (C) 2026 The Loom / Stefan Schulz. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://www.the-loom.de
 *
 * Checks the counting queries against the fixture matrix in .docker/fixtures.sql.
 *
 * These are not unit tests and deliberately use no mocks: what has to be right here is
 * the SQL — nested-set traversal, publishing dates, access levels — and a mocked
 * database would only assert that the query looks the way it was written, not that it
 * counts the right rows. So it runs against the real Joomla install of the test stack.
 *
 *   .docker/counts.sh
 *
 * The expected values, and the reasoning behind each one, live at the bottom of
 * .docker/fixtures.sql. If a number here changes, that file changes with it.
 */

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use TheLoom\Module\DinkyMetrics\Site\Helper\Counter;

// phpcs:disable PSR1.Files.SideEffects
\define('_JEXEC', 1);
\define('JPATH_BASE', getenv('JOOMLA_ROOT') ?: '/var/www/html');
// phpcs:enable PSR1.Files.SideEffects

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

// The module's own classes: the extension autoloader only knows them once the extension
// is being rendered, so register the one prefix this script needs.
spl_autoload_register(static function (string $class): void {
    $prefix = 'TheLoom\\Module\\DinkyMetrics\\Site\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = __DIR__ . '/../../src/' . str_replace('\\', '/', substr($class, \strlen($prefix))) . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});

$db      = Factory::getContainer()->get(DatabaseInterface::class);
$counter = new Counter($db);
$now     = Factory::getDate()->toSql();

// What an anonymous visitor is allowed to see. Access::getAuthorisedViewLevels() would
// need a running application, and for a script whose job is to check SQL the view levels
// are simply an input — so they are stated here and sanity-checked against the install,
// which also documents what the numbers in this file assume.
$guest    = [1, 5];
$expected = ['1' => 'Public', '5' => 'Guest'];
$levels   = $db->setQuery(
    $db->getQuery(true)
        ->select([$db->quoteName('id'), $db->quoteName('title')])
        ->from($db->quoteName('#__viewlevels'))
        ->whereIn($db->quoteName('id'), $guest)
)->loadAssocList('id', 'title');

if ($levels !== $expected) {
    fwrite(
        STDERR,
        "The install's view levels are not the stock Public/Guest pair this file assumes:\n  "
        . json_encode($levels) . "\nRe-check the expected counts before trusting a green run.\n"
    );

    exit(2);
}

$failed = 0;
$run    = 0;

/**
 * @param   string     $what      What is being counted.
 * @param   int        $expected  The value documented in fixtures.sql.
 * @param   int        $actual    What the query returned.
 * @param   string     $why       The reasoning, for the failure message.
 *
 * @return  void
 */
$check = static function (string $what, int $expected, int $actual, string $why) use (&$failed, &$run): void {
    $run++;

    if ($expected === $actual) {
        printf("  ok    %-52s %d\n", $what, $actual);

        return;
    }

    $failed++;
    printf("  FAIL  %-52s expected %d, got %d\n        %s\n", $what, $expected, $actual, $why);
};

printf("Counting against the fixtures (guest view levels: %s)\n\n", implode(',', $guest));

echo "content_count\n";

$check(
    'category 601 + children, published',
    8,
    $counter->articles(601, true, 'published', 'any', $guest, null, $now),
    '651 + 611,612,618,619 + 621,622 + 631; 617 drops on access, 613/614 on status, '
    . '615/616 on the publishing dates, 641 because its category is unpublished'
);

$check(
    'category 601 + children, featured only',
    2,
    $counter->articles(601, true, 'published', 'only', $guest, null, $now),
    '612 and 622'
);

$check(
    'category 601 + children, featured excluded',
    6,
    $counter->articles(601, true, 'published', 'exclude', $guest, null, $now),
    'the eight above minus the two featured ones'
);

$check(
    'category 601 + children, ignoring access',
    9,
    $counter->articles(601, true, 'published', 'any', null, null, $now),
    '617 joins in when count_all_access is on'
);

$check(
    'category 602 only, published',
    4,
    $counter->articles(602, false, 'published', 'any', $guest, null, $now),
    '611, 612, 618, 619 — the grandchild category is not included'
);

$check(
    'category 602 only, any status',
    8,
    $counter->articles(602, false, 'any', 'any', $guest, null, $now),
    'all nine articles in 602 except 617, which is still filtered on access'
);

$check(
    'category 602 only, published or unpublished',
    7,
    $counter->articles(602, false, 'published_unpublished', 'any', $guest, null, $now),
    'state 0 or 1 and nothing else: 611, 612, 613, 615, 616, 618, 619. The publishing '
    . 'dates are deliberately not applied here — only "published" honours them — so the '
    . 'not-yet and expired articles count. 614 is trashed, 617 is restricted'
);

$check(
    'category 606 (unpublished category), published',
    0,
    $counter->articles(606, false, 'published', 'any', $guest, null, $now),
    'decision E2: its article is published but the category is not'
);

$check(
    'category 606 (unpublished category), any status',
    1,
    $counter->articles(606, false, 'any', 'any', $guest, null, $now),
    'without the published filter the category state is not considered'
);

echo "\ncategory_count\n";

$check(
    'below 601, direct children, published',
    3,
    $counter->categories('com_content', 601, false, 'published', false, $guest, $now),
    '602, 604, 605 — 606 is unpublished'
);

$check(
    'below 601, direct children, skipping empty',
    2,
    $counter->categories('com_content', 601, false, 'published', true, $guest, $now),
    '605 holds no published article'
);

$check(
    'below 601, every level, published',
    4,
    $counter->categories('com_content', 601, true, 'published', false, $guest, $now),
    '602, 603, 604, 605 — the parent itself is never counted'
);

$check(
    'below 601, every level, any status',
    5,
    $counter->categories('com_content', 601, true, 'any', false, $guest, $now),
    '606 joins in'
);

$check(
    'below 602, direct children, published',
    1,
    $counter->categories('com_content', 602, false, 'published', false, $guest, $now),
    'just 603'
);

$check(
    'below 605 (empty category)',
    0,
    $counter->categories('com_content', 605, false, 'published', false, $guest, $now),
    'no subcategories at all'
);

$check(
    'contact categories below 601',
    0,
    $counter->categories('com_contact', 601, true, 'published', false, $guest, $now),
    'the extension filter has to bite: these categories are com_content'
);

printf("\n%d/%d checks passed\n", $run - $failed, $run);

exit($failed === 0 ? 0 : 1);
