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
 * Whole units of time between a date and now — the "since 1996" kind of figure.
 *
 * Both ends are passed in, so the result depends on nothing but its arguments and the
 * class stays testable across a leap day without touching the system clock.
 */
final class Elapsed
{
    /**
     * The units a figure may ask for. Anything else falls back to the first one.
     */
    public const UNITS = ['years', 'months', 'days'];

    /**
     * Read a date as the editorial team types it.
     *
     * Accepts "1996-05-15" and, because that is what a value stored by Joomla's calendar
     * field looks like, "1996-05-15 00:00:00" and its ISO variant with a T. Anything else
     * — including a well-formed but impossible date such as 2026-02-30 — returns null,
     * which the caller turns into a skipped figure.
     *
     * @param   string        $raw  The configured value.
     * @param   \DateTimeZone $tz   The site time zone the value is meant in.
     *
     * @return  \DateTimeImmutable|null
     */
    public static function parse(string $raw, \DateTimeZone $tz): ?\DateTimeImmutable
    {
        $value = trim($raw);

        if ($value === '') {
            return null;
        }

        // Mirror the pattern the parameter form enforces. createFromFormat's "Y" would
        // otherwise accept one to four digits, turning the typo "96-05-15" into the year
        // 96 AD and the figure into a couple of millennia.
        if (!preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/', $value)) {
            return null;
        }

        // Every format is anchored with "!" so that fields the format does not mention are
        // zeroed instead of inherited from the current time — otherwise "1996-05-15 00:00"
        // would silently pick up today's seconds.
        foreach (['!Y-m-d', '!Y-m-d H:i:s', '!Y-m-d H:i', '!Y-m-d\TH:i:s', '!Y-m-d\TH:i'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value, $tz);

            if ($date === false) {
                continue;
            }

            // createFromFormat is forgiving: 2026-02-30 silently becomes 2026-03-02, and a
            // date with trailing data still parses. Both show up as warnings.
            $errors = \DateTimeImmutable::getLastErrors();

            if (\is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                continue;
            }

            return $date;
        }

        return null;
    }

    /**
     * Whole units between two moments, truncated rather than rounded.
     *
     * A date in the future gives 0 — a figure reading "-3 years" is never what was meant,
     * and the caller logs the case instead of showing it.
     *
     * @param   \DateTimeInterface  $from  The earlier moment.
     * @param   \DateTimeInterface  $now   The moment to measure to.
     * @param   string              $unit  One of self::UNITS.
     *
     * @return  int
     */
    public static function since(\DateTimeInterface $from, \DateTimeInterface $now, string $unit): int
    {
        if ($from >= $now) {
            return 0;
        }

        $diff = $from->diff($now);

        return match (\in_array($unit, self::UNITS, true) ? $unit : self::UNITS[0]) {
            'months' => $diff->y * 12 + $diff->m,
            'days'   => (int) $diff->days,
            default  => $diff->y,
        };
    }
}
