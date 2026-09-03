<?php

/**
 * @package     TheLoom.Module
 * @subpackage  Site.DinkyMetrics
 *
 * @copyright   Copyright (C) 2026 The Loom / Stefan Schulz. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://www.the-loom.de
 */

namespace TheLoom\Module\DinkyMetrics\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheLoom\Module\DinkyMetrics\Site\Helper\Elapsed;

final class ElapsedTest extends TestCase
{
    private const TZ = 'Europe/Berlin';

    private static function at(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value, new \DateTimeZone(self::TZ));
    }

    /**
     * @return  array<string, array{0: string, 1: string, 2: string, 3: int}>
     */
    public static function sinceCases(): array
    {
        return [
            // The everyday case: a masthead that has said "since 1996" for thirty years.
            'whole years'                => ['1996-05-15', '2026-09-03', 'years', 30],

            // Truncation, not rounding: eleven months and 29 days is still not a year.
            'a day short of a year'      => ['2025-09-04', '2026-09-03', 'years', 0],
            'exactly a year'             => ['2025-09-03', '2026-09-03', 'years', 1],

            // Leap day: 29 February only "comes round" on 1 March in a common year.
            'leap day, not yet'          => ['2024-02-29', '2025-02-28', 'years', 0],
            'leap day, just made it'     => ['2024-02-29', '2025-03-01', 'years', 1],
            'leap day to leap day'       => ['2024-02-29', '2028-02-29', 'years', 4],

            // Year boundary: December to January is a new calendar year but not a year.
            'across new year'            => ['2025-12-31', '2026-01-01', 'years', 0],
            'across new year in days'    => ['2025-12-31', '2026-01-01', 'days', 1],

            'months across a year'       => ['2025-11-15', '2026-09-03', 'months', 9],
            'months, same month'         => ['2026-09-01', '2026-09-03', 'months', 0],
            'days over a common year'    => ['2026-01-01', '2026-09-03', 'days', 245],
            'days over a leap year'      => ['2024-01-01', '2024-09-03', 'days', 246],

            // A date in the future is never shown as a negative figure.
            'future date'                => ['2030-01-01', '2026-09-03', 'years', 0],
            'future date in days'        => ['2026-09-04', '2026-09-03', 'days', 0],
            'same moment'                => ['2026-09-03', '2026-09-03', 'years', 0],

            // An unknown unit falls back to years rather than to zero.
            'unknown unit means years'   => ['1996-05-15', '2026-09-03', 'decades', 30],
        ];
    }

    #[DataProvider('sinceCases')]
    public function testSince(string $from, string $now, string $unit, int $expected): void
    {
        $this->assertSame($expected, Elapsed::since(self::at($from), self::at($now), $unit));
    }

    /**
     * @return  array<string, array{0: string, 1: string|null}>
     */
    public static function parseCases(): array
    {
        return [
            'plain date'            => ['1996-05-15', '1996-05-15 00:00:00'],
            'with time'             => ['1996-05-15 13:45:00', '1996-05-15 13:45:00'],
            'with time, no seconds' => ['1996-05-15 13:45', '1996-05-15 13:45:00'],
            'ISO with T'            => ['1996-05-15T13:45:00', '1996-05-15 13:45:00'],
            'surrounding space'     => ['  1996-05-15  ', '1996-05-15 00:00:00'],

            'empty'                 => ['', null],
            'blank'                 => ['   ', null],
            'impossible day'        => ['2026-02-30', null],
            'impossible month'      => ['2026-13-01', null],
            'german notation'       => ['15.05.1996', null],
            'day first'             => ['15-05-1996', null],
            'words'                 => ['yesterday', null],
            'sql injection attempt' => ["1996-05-15' OR 1=1", null],
            'two digit year'        => ['96-05-15', null],
        ];
    }

    #[DataProvider('parseCases')]
    public function testParse(string $raw, ?string $expected): void
    {
        $parsed = Elapsed::parse($raw, new \DateTimeZone(self::TZ));

        if ($expected === null) {
            $this->assertNull($parsed);

            return;
        }

        $this->assertNotNull($parsed);
        $this->assertSame($expected, $parsed->format('Y-m-d H:i:s'));
        $this->assertSame(self::TZ, $parsed->getTimezone()->getName());
    }

    /**
     * A value carrying no time must not pick up the current one, or the same
     * configuration would produce a different number of days depending on the hour
     * the page happened to be rendered.
     */
    public function testParseZeroesTheTimeItWasNotGiven(): void
    {
        $tz = new \DateTimeZone(self::TZ);

        $this->assertSame('00:00:00', Elapsed::parse('1996-05-15', $tz)?->format('H:i:s'));
        $this->assertSame('00', Elapsed::parse('1996-05-15 13:45', $tz)?->format('s'));
    }
}
