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
use TheLoom\Module\DinkyMetrics\Site\Helper\Formatter;

/**
 * The server half of the formatting-parity check required by the specification.
 *
 * tests/parity/cases.json holds inputs and the string Intl.NumberFormat produces for
 * them in a browser; this asserts the PHP side produces the same, and
 * tests/parity/parity.mjs asserts the JS side still does. Run both and the two
 * implementations are pinned to each other.
 */
final class FormatterTest extends TestCase
{
    /**
     * @return  array<string, array{0: int|float, 1: string, 2: int, 3: bool, 4: string}>
     */
    public static function parityCases(): array
    {
        $file  = __DIR__ . '/../parity/cases.json';
        $cases = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        $data = [];

        foreach ($cases as $case) {
            $name = sprintf(
                '%s %s %dd %s',
                $case['value'],
                $case['locale'],
                $case['decimals'],
                $case['grouping'] ? 'grouped' : 'plain'
            );

            $data[$name] = [
                $case['value'],
                $case['locale'],
                $case['decimals'],
                $case['grouping'],
                $case['expected'],
            ];
        }

        return $data;
    }

    #[DataProvider('parityCases')]
    public function testMatchesIntlNumberFormat(
        int|float $value,
        string $locale,
        int $decimals,
        bool $grouping,
        string $expected
    ): void {
        $formatter = new Formatter($locale, $decimals, $grouping);

        $this->assertSame($expected, $formatter->format($value));
    }

    /**
     * ICU rounds half to even by default, Intl.NumberFormat rounds half away from zero.
     * Getting this wrong is invisible until a figure happens to end in .5, so it is
     * asserted on its own rather than left to the parity fixtures.
     */
    public function testRoundsHalfAwayFromZeroLikeTheBrowser(): void
    {
        $formatter = new Formatter('en-GB', 0, false);

        $this->assertSame('1', $formatter->format(0.5), 'half-even would give 0');
        $this->assertSame('2', $formatter->format(1.5));
        $this->assertSame('3', $formatter->format(2.5), 'half-even would give 2');
        $this->assertSame('4', $formatter->format(3.5));
    }

    public function testGroupingCanBeTurnedOff(): void
    {
        $this->assertSame('1400', (new Formatter('de-DE', 0, false))->format(1400));
        $this->assertSame('1.400', (new Formatter('de-DE', 0, true))->format(1400));
    }

    /**
     * "Decimal places" is a ceiling, not a fixed width — the whole point for this
     * module's count / years-since figures, which are always whole numbers and must
     * never grow a fake ".00" just because the instance's format is set to two decimals
     * for the sake of some other, genuinely fractional figure in the same list.
     */
    public function testDecimalsAreACeilingNotAPaddedWidth(): void
    {
        $this->assertSame('42', (new Formatter('en-GB', 2, false))->format(42));
        $this->assertSame('42', (new Formatter('en-GB', 0, false))->format(42));
        $this->assertSame('42.5', (new Formatter('en-GB', 2, false))->format(42.5));

        // A value that rounds up to a whole number drops the decimal too, not just a
        // value that already was one.
        $this->assertSame('1,235', (new Formatter('en-GB', 1, true))->format(1234.96));
    }

    /**
     * @return  array<string, array{0: string|null, 1: string}>
     */
    public static function localeCases(): array
    {
        return [
            'empty falls back'      => ['', 'en-GB'],
            'null falls back'       => [null, 'en-GB'],
            'whitespace only'       => ['   ', 'en-GB'],
            'language only'         => ['de', 'de'],
            'language and region'   => ['de-DE', 'de-DE'],
            'underscore accepted'   => ['de_DE', 'de-DE'],
            'case is normalised'    => ['DE-de', 'de-DE'],
            'script subtag'         => ['zh-hant-tw', 'zh-Hant-TW'],
            'numeric region'        => ['es-419', 'es-419'],
            'garbage falls back'    => ['not a locale', 'en-GB'],
            'injection falls back'  => ["de-DE'; DROP", 'en-GB'],
            'too long falls back'   => ['deutsch', 'en-GB'],
        ];
    }

    #[DataProvider('localeCases')]
    public function testNormaliseLocale(?string $raw, string $expected): void
    {
        $this->assertSame($expected, Formatter::normaliseLocale($raw));
    }

    public function testIsExactReportsWhetherServerAndBrowserAgree(): void
    {
        $this->assertTrue(
            (new Formatter('de-DE', 0, true))->isExact(),
            'de-DE is covered by ICU and by the fallback table'
        );

        if (\extension_loaded('intl')) {
            $this->assertTrue(
                (new Formatter('hi-IN', 0, true))->isExact(),
                'with intl every locale is exact'
            );

            return;
        }

        $this->assertFalse(
            (new Formatter('hi-IN', 0, true))->isExact(),
            'without intl an unknown locale cannot be guaranteed'
        );
    }
}
