<?php

/**
 * @package     TheLoom.Module
 * @subpackage  Site.DinkyMetrics
 *
 * @copyright   Copyright (C) 2026 The Loom / Stefan Schulz. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://www.the-loom.de
 */

namespace TheLoom\Module\DinkyMetrics\Site\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Turns a raw figure into the string that goes into the page.
 *
 * The contract this class has to honour is parity: for the same value, locale, number
 * of decimals and grouping flag it must produce exactly what
 * Intl.NumberFormat(locale, {minimumFractionDigits, maximumFractionDigits, useGrouping})
 * produces in the browser, because the count-up animation ends by writing the browser's
 * own formatting into an element whose server-rendered content was written here. Any
 * difference would show up as the number visibly changing when the animation finishes.
 *
 * Three details carry that parity:
 *
 * - Rounding. ICU defaults to half-even ("banker's rounding"), Intl.NumberFormat defaults
 *   to halfExpand (half away from zero). 2.5 with no decimals is 2 under the first and 3
 *   under the second, so the rounding mode is set explicitly.
 * - Decimals are a ceiling, not a fixed width. "Decimal places" configures the *most* a
 *   figure may show, the same way Intl.NumberFormat's maximumFractionDigits works with
 *   its minimumFractionDigits left at the default of 0: a years_since or content_count
 *   figure is always a whole number and appears as one ("30", not "30.0") regardless of
 *   the setting, while a literal figure that genuinely carries a fraction ("1234.5")
 *   still shows it, up to the configured maximum.
 * - The intl extension. With it, both sides are the same ICU implementation. Without it
 *   the fallback below only knows a handful of separator conventions; see isExact().
 */
final class Formatter
{
    /**
     * Decimal and grouping separators per language, for the no-intl fallback.
     *
     * Deliberately short: these are the conventions this module has been checked
     * against. Everything else falls back to the English pattern, which is why
     * isExact() exists.
     */
    private const FALLBACK_SEPARATORS = [
        'de' => [',', '.'],
        'en' => ['.', ','],
        'es' => [',', '.'],
        'it' => [',', '.'],
        'nl' => [',', '.'],
        'pt' => [',', '.'],
        'da' => [',', '.'],
        'fr' => [',', "\u{202F}"],
        'sv' => [',', "\u{00A0}"],
        'pl' => [',', "\u{00A0}"],
        'cs' => [',', "\u{00A0}"],
    ];

    private const DEFAULT_SEPARATORS = ['.', ','];

    private ?\NumberFormatter $formatter = null;

    /**
     * @param   string  $locale    BCP-47 tag, already normalised by normaliseLocale().
     * @param   int     $decimals  Maximum number of decimal places, 0 or more. A value
     *                             needing fewer shows fewer; trailing zeros are trimmed.
     * @param   bool    $grouping  Whether to separate thousands.
     */
    public function __construct(
        private readonly string $locale,
        private readonly int $decimals,
        private readonly bool $grouping
    ) {
        if (!\extension_loaded('intl')) {
            return;
        }

        $formatter = new \NumberFormatter($this->locale, \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 0);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, max(0, $this->decimals));
        $formatter->setAttribute(\NumberFormatter::GROUPING_USED, $this->grouping ? 1 : 0);

        // Match Intl.NumberFormat's default roundingMode "halfExpand", not ICU's half-even.
        $formatter->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFUP);

        $this->formatter = $formatter;
    }

    /**
     * Format one figure for display.
     *
     * @param   int|float  $value  The raw figure.
     *
     * @return  string
     */
    public function format(int|float $value): string
    {
        if ($this->formatter !== null) {
            $formatted = $this->formatter->format($value);

            if ($formatted !== false) {
                return $formatted;
            }
        }

        [$decimalPoint, $thousandsSeparator] = self::fallbackSeparators($this->locale);

        // number_format() always pads to the exact width given, so the "ceiling, not a
        // fixed width" behaviour above needs a second pass: round with a neutral '.' via
        // number_format's own correct rounding, trim the trailing zeros it left behind,
        // then measure what actually remains and render that with the real separators.
        $rounded  = number_format($value, max(0, $this->decimals), '.', '');
        $trimmed  = str_contains($rounded, '.') ? rtrim(rtrim($rounded, '0'), '.') : $rounded;
        $decimals = str_contains($trimmed, '.') ? \strlen($trimmed) - strpos($trimmed, '.') - 1 : 0;

        return number_format((float) $trimmed, $decimals, $decimalPoint, $this->grouping ? $thousandsSeparator : '');
    }

    /**
     * Whether this instance formats exactly like the browser will.
     *
     * False means the intl extension is missing and the locale is not one of the
     * conventions the fallback knows, so server and client output may differ in the
     * separators. The caller is expected to log that once, not to change behaviour.
     *
     * @return  bool
     */
    public function isExact(): bool
    {
        return $this->formatter !== null
            || isset(self::FALLBACK_SEPARATORS[self::language($this->locale)]);
    }

    /**
     * Validate and tidy a language tag.
     *
     * Accepts the shapes that matter here — "de", "de-DE", "zh-Hant-TW" — and returns
     * the fallback for anything else, so a typo in the module parameter can never reach
     * NumberFormatter or Intl.NumberFormat.
     *
     * @param   string|null  $raw       The configured value, may be empty.
     * @param   string       $fallback  Tag to use when $raw is empty or malformed.
     *
     * @return  string
     */
    public static function normaliseLocale(?string $raw, string $fallback = 'en-GB'): string
    {
        $tag = trim((string) $raw);

        if ($tag === '') {
            return $fallback;
        }

        $tag = str_replace('_', '-', $tag);

        if (!preg_match('/^([A-Za-z]{2,3})(?:-([A-Za-z]{4}))?(?:-([A-Za-z]{2}|[0-9]{3}))?$/', $tag, $m)) {
            return $fallback;
        }

        $parts = [strtolower($m[1])];

        if (($m[2] ?? '') !== '') {
            $parts[] = ucfirst(strtolower($m[2]));
        }

        if (($m[3] ?? '') !== '') {
            $parts[] = strtoupper($m[3]);
        }

        return implode('-', $parts);
    }

    /**
     * The separators to use when the intl extension is unavailable.
     *
     * @param   string  $locale  A normalised language tag.
     *
     * @return  array{0: string, 1: string}  Decimal point and thousands separator.
     */
    private static function fallbackSeparators(string $locale): array
    {
        return self::FALLBACK_SEPARATORS[self::language($locale)] ?? self::DEFAULT_SEPARATORS;
    }

    /**
     * The language subtag of a language tag.
     *
     * @param   string  $locale  A normalised language tag.
     *
     * @return  string
     */
    private static function language(string $locale): string
    {
        return strtolower(explode('-', $locale)[0]);
    }
}
