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

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Turns the module's parameters into the finished figures the layout prints.
 *
 * resolve() is the extension point: it is a switch over the figure's `source`, and a new
 * source is a new case here plus a new option in the manifest's `source` field. Nothing
 * else in the module needs to know about it.
 *
 * One thing to know before adding a case: Joomla's subform saves *every* field of every
 * row, including the ones hidden by showon, each with its default. A years_since row
 * therefore carries `featured`, `state` and `include_children` too. Never infer what a
 * row means from which keys are present — dispatch on `source` and read only the keys
 * that source owns.
 */
class DinkyMetricsHelper implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    private const SOURCES = ['literal', 'years_since', 'content_count', 'category_count'];

    private const EXTENSIONS = ['com_content', 'com_contact', 'com_newsfeeds', 'com_banners'];

    private const LOG_CATEGORY = 'mod_dinkymetrics';

    /**
     * @param   array  $config  Configuration passed in by the helper factory.
     */
    public function __construct(private readonly array $config = [])
    {
    }

    /**
     * Every figure that has something to show, in the order the editor put them in.
     *
     * Rows without a caption, and rows whose value cannot be worked out, are dropped
     * rather than rendered as a placeholder — a metrics band with a gap in it looks
     * broken, an absent figure does not.
     *
     * @param   Registry         $params  The module parameters.
     * @param   SiteApplication  $app     The application.
     *
     * @return  array<int, array{label: string, raw: int|float, formatted: string, prefix: string,
     *                           suffix: string, aria: string, href: string|null, external: bool}>
     */
    public function getFigures(Registry $params, SiteApplication $app): array
    {
        $rows = $params->get('figures');

        if (empty($rows)) {
            return [];
        }

        $locale    = Formatter::normaliseLocale(
            (string) $params->get('format_locale', ''),
            $app->getLanguage()->getTag()
        );
        $formatter = new Formatter(
            $locale,
            max(0, (int) $params->get('format_decimals', 0)),
            $params->get('format_grouping', 'yes') !== 'no'
        );

        if (!$formatter->isExact()) {
            $this->log(
                $params,
                sprintf(
                    'The intl extension is missing and "%s" is not in the fallback table; '
                    . 'the server and the browser may format numbers differently.',
                    $locale
                ),
                Log::WARNING
            );
        }

        $figures = [];

        foreach ((array) $rows as $key => $row) {
            $row   = (array) $row;
            $label = trim((string) ($row['label'] ?? ''));

            if ($label === '') {
                $this->log($params, sprintf('Skipped figure "%s": no caption.', $key));
                continue;
            }

            $value = $this->resolve($row, $params, $app);

            if ($value === null) {
                $this->log($params, sprintf('Skipped figure "%s" ("%s"): unresolvable.', $key, $label));
                continue;
            }

            $prefix    = (string) ($row['prefix'] ?? '');
            $suffix    = (string) ($row['suffix'] ?? '');
            $formatted = $formatter->format($value);
            $link      = $this->resolveLink((string) ($row['link'] ?? ''));

            $figures[] = [
                'label'     => $label,
                'raw'       => $value,
                'formatted' => $formatted,
                'prefix'    => $prefix,
                'suffix'    => $suffix,
                // One deterministic string for screen readers, so they never read out an
                // intermediate value of the count-up. Spaced like the specification's
                // example ("≈ 348+"): the prefix reads as its own word, the suffix binds
                // to the number the way a percent sign has to.
                'aria'      => trim($prefix . ' ' . $formatted . $suffix),
                'href'      => $link['href'],
                'external'  => $link['external'],
            ];
        }

        return $figures;
    }

    /**
     * Work out one figure's value.
     *
     * Public and safe to override: a site that needs another source can subclass this
     * helper and extend the switch.
     *
     * @param   array            $figure  One row of the figures subform.
     * @param   Registry         $params  The module parameters.
     * @param   SiteApplication  $app     The application.
     *
     * @return  int|float|null  Null when the row cannot produce a number.
     */
    public function resolve(array $figure, Registry $params, SiteApplication $app): int|float|null
    {
        $source = self::oneOf($figure['source'] ?? null, self::SOURCES, 'literal');

        return match ($source) {
            'literal'        => $this->literal($figure),
            'years_since'    => $this->yearsSince($figure, $params, $app),
            'content_count'  => $this->contentCount($figure, $params, $app),
            'category_count' => $this->categoryCount($figure, $params, $app),
        };
    }

    /**
     * A number the editorial team typed in.
     *
     * @param   array  $figure  The figure row.
     *
     * @return  int|float|null
     */
    private function literal(array $figure): int|float|null
    {
        $raw = trim((string) ($figure['value'] ?? ''));

        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }

        return $raw == (int) $raw ? (int) $raw : (float) $raw;
    }

    /**
     * Whole units of time since a date.
     *
     * @param   array            $figure  The figure row.
     * @param   Registry         $params  The module parameters.
     * @param   SiteApplication  $app     The application.
     *
     * @return  int|null
     */
    private function yearsSince(array $figure, Registry $params, SiteApplication $app): ?int
    {
        $timezone = new \DateTimeZone($app->get('offset', 'UTC'));
        $date     = Elapsed::parse((string) ($figure['date'] ?? ''), $timezone);

        if ($date === null) {
            return null;
        }

        $now = new \DateTimeImmutable('now', $timezone);

        if ($date >= $now) {
            // Specified behaviour: show 0 rather than a negative figure or an error, and
            // leave a trace for whoever set the date.
            $this->log(
                $params,
                sprintf('Figure date "%s" is in the future; showing 0.', $date->format('Y-m-d')),
                Log::WARNING
            );

            return 0;
        }

        return Elapsed::since($date, $now, self::oneOf($figure['unit'] ?? null, Elapsed::UNITS, 'years'));
    }

    /**
     * Number of articles matching the row's filters.
     *
     * @param   array            $figure  The figure row.
     * @param   Registry         $params  The module parameters.
     * @param   SiteApplication  $app     The application.
     *
     * @return  int
     */
    private function contentCount(array $figure, Registry $params, SiteApplication $app): int
    {
        $category = (int) ($figure['category'] ?? 0);

        return $this->counter()->articles(
            categoryId: $category > 0 ? $category : null,
            includeChildren: ($figure['include_children'] ?? 'yes') !== 'no',
            state: self::oneOf($figure['state'] ?? null, Counter::STATES, 'published'),
            featured: self::oneOf($figure['featured'] ?? null, Counter::FEATURED, 'any'),
            viewLevels: $this->viewLevels($params, $app),
            language: $this->language($app),
            now: Factory::getDate()->toSql()
        );
    }

    /**
     * Number of categories matching the row's filters.
     *
     * @param   array            $figure  The figure row.
     * @param   Registry         $params  The module parameters.
     * @param   SiteApplication  $app     The application.
     *
     * @return  int
     */
    private function categoryCount(array $figure, Registry $params, SiteApplication $app): int
    {
        $extension = self::oneOf($figure['extension'] ?? null, self::EXTENSIONS, 'com_content');
        $parent    = (int) ($figure['parent'] ?? 0);

        return $this->counter()->categories(
            extension: $extension,
            parentId: $parent > 0 ? $parent : null,
            includeChildren: ($figure['include_children_cat'] ?? 'no') === 'yes',
            state: self::oneOf($figure['state'] ?? null, Counter::STATES, 'published'),
            // Only meaningful for article categories, and the form only offers it there.
            skipEmpty: $extension === 'com_content' && ($figure['skip_empty'] ?? 'no') === 'yes',
            viewLevels: $this->viewLevels($params, $app),
            now: Factory::getDate()->toSql()
        );
    }

    /**
     * The access levels a count has to stay within, or null to count everything.
     *
     * @param   Registry         $params  The module parameters.
     * @param   SiteApplication  $app     The application.
     *
     * @return  int[]|null
     */
    private function viewLevels(Registry $params, SiteApplication $app): ?array
    {
        if ($params->get('count_all_access', 'no') === 'yes') {
            return null;
        }

        return $app->getIdentity()->getAuthorisedViewLevels();
    }

    /**
     * The language to count, or null on a site that is not multilingual.
     *
     * @param   SiteApplication  $app  The application.
     *
     * @return  string|null
     */
    private function language(SiteApplication $app): ?string
    {
        return Multilanguage::isEnabled() ? $app->getLanguage()->getTag() : null;
    }

    /**
     * Validate a figure's link and work out how to render it.
     *
     * Internal targets go through the router so they follow the site's SEF settings;
     * external ones are passed through untouched but marked, so the layout can add
     * rel="noopener". Anything that is neither — a javascript: or data: URL, or something
     * that is not a URL at all — yields no link rather than a broken or unsafe one.
     *
     * @param   string  $raw  The configured link.
     *
     * @return  array{href: string|null, external: bool}
     */
    private function resolveLink(string $raw): array
    {
        $none = ['href' => null, 'external' => false];
        $link = trim($raw);

        if ($link === '') {
            return $none;
        }

        $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));

        if ($scheme === '') {
            // Relative: an index.php?… route, or a path on this site. Any raw whitespace
            // means this was never a URL — a real path would carry %20 — and routing it
            // anyway would produce a broken link instead of no link.
            if (preg_match('/\s/', $link)) {
                return $none;
            }

            return ['href' => Route::_($link), 'external' => false];
        }

        if (!\in_array($scheme, ['http', 'https', 'mailto'], true)) {
            return $none;
        }

        if ($scheme !== 'mailto' && !filter_var($link, FILTER_VALIDATE_URL)) {
            return $none;
        }

        return ['href' => $link, 'external' => true];
    }

    /**
     * A counter bound to the module's database connection.
     *
     * @return  Counter
     */
    private function counter(): Counter
    {
        return new Counter($this->getDatabase());
    }

    /**
     * Whitelist a value against the options the manifest offers.
     *
     * @param   mixed     $value    The stored value.
     * @param   string[]  $allowed  The permitted values.
     * @param   string    $default  Fallback for anything else.
     *
     * @return  string
     */
    private static function oneOf(mixed $value, array $allowed, string $default): string
    {
        $value = \is_string($value) ? $value : '';

        return \in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * Record why a figure did not make it into the output — only when debugging is on.
     *
     * @param   Registry  $params    The module parameters.
     * @param   string    $message   What happened.
     * @param   int       $priority  A Log priority constant.
     *
     * @return  void
     */
    private function log(Registry $params, string $message, int $priority = Log::INFO): void
    {
        if ((int) $params->get('debug', 0) !== 1) {
            return;
        }

        Log::add($message, $priority, self::LOG_CATEGORY);
    }
}
