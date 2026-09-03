<?php

/**
 * @package     TheLoom.Module
 * @subpackage  Site.DinkyMetrics
 *
 * @copyright   Copyright (C) 2026 The Loom / Stefan Schulz. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://www.the-loom.de
 */

namespace TheLoom\Module\DinkyMetrics\Site\Field;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Builds the one-line, human-readable summary shown for a collapsed figure row.
 *
 * Presentation only: nothing here feeds DinkyMetricsHelper::resolve(), so a bug in a
 * summary sentence can misdescribe a row but can never change what the module shows on
 * the site. The enum labels are hand-mirrored from the <option> texts in
 * mod_dinkymetrics.xml rather than read back out of the field/form objects — ListField's
 * own option builder is protected, and reflecting into it would be more fragile than
 * keeping two short, obviously-paired lists in step (the same trade-off
 * DinkyMetricsHelper::oneOf() already makes for the same values).
 */
final class FigureSummary
{
    private const SOURCE_LABELS = [
        'literal'        => 'MOD_DINKYMETRICS_FIGURE_SOURCE_LITERAL',
        'years_since'    => 'MOD_DINKYMETRICS_FIGURE_SOURCE_YEARS_SINCE',
        'content_count'  => 'MOD_DINKYMETRICS_FIGURE_SOURCE_CONTENT_COUNT',
        'category_count' => 'MOD_DINKYMETRICS_FIGURE_SOURCE_CATEGORY_COUNT',
    ];

    private const UNIT_LABELS = [
        'years'  => 'MOD_DINKYMETRICS_FIGURE_UNIT_YEARS',
        'months' => 'MOD_DINKYMETRICS_FIGURE_UNIT_MONTHS',
        'days'   => 'MOD_DINKYMETRICS_FIGURE_UNIT_DAYS',
    ];

    private const STATE_LABELS = [
        'published'             => 'MOD_DINKYMETRICS_FIGURE_STATE_PUBLISHED',
        'published_unpublished' => 'MOD_DINKYMETRICS_FIGURE_STATE_PUBLISHED_UNPUBLISHED',
        'any'                   => 'MOD_DINKYMETRICS_FIGURE_STATE_ANY',
    ];

    private const EXTENSION_LABELS = [
        'com_content'   => 'MOD_DINKYMETRICS_FIGURE_EXTENSION_CONTENT',
        'com_contact'   => 'MOD_DINKYMETRICS_FIGURE_EXTENSION_CONTACT',
        'com_newsfeeds' => 'MOD_DINKYMETRICS_FIGURE_EXTENSION_NEWSFEEDS',
        'com_banners'   => 'MOD_DINKYMETRICS_FIGURE_EXTENSION_BANNERS',
    ];

    /** @var array<int, string|null> Request-local cache; a handful of rows at most. */
    private static array $categoryTitles = [];

    /**
     * @param   Form  $row  One row's Form instance, as handed to the layout.
     *
     * @return  array{caption: string, source: string, detail: string}
     */
    public static function summarise(Form $row): array
    {
        $source = self::string($row, 'source', 'literal');
        $label  = trim(self::string($row, 'label', ''));

        return [
            'caption' => $label !== '' ? $label : Text::_('MOD_DINKYMETRICS_FIGURE_SUMMARY_NO_LABEL'),
            'source'  => Text::_(self::SOURCE_LABELS[$source] ?? self::SOURCE_LABELS['literal']),
            'detail'  => match ($source) {
                'years_since'    => self::yearsSince($row),
                'content_count'  => self::contentCount($row),
                'category_count' => self::categoryCount($row),
                default          => self::literal($row),
            },
        ];
    }

    /**
     * @param   Form  $row  The row.
     *
     * @return  string
     */
    private static function literal(Form $row): string
    {
        $value = trim(self::string($row, 'value', ''));

        if ($value === '') {
            return Text::_('MOD_DINKYMETRICS_FIGURE_SUMMARY_NO_VALUE');
        }

        return self::withPrefixSuffix($row, $value);
    }

    /**
     * @param   Form  $row  The row.
     *
     * @return  string
     */
    private static function yearsSince(Form $row): string
    {
        // Drop a trailing midnight time: existing figures still carry it from before the
        // date field became plain text (see .doc/WORKPLAN.md, risk R2), and it adds
        // nothing to the summary either way.
        $date = trim(self::string($row, 'date', ''));
        $date = preg_replace('/ 00:00:00$/', '', $date);
        $unit = self::string($row, 'unit', 'years');
        $unit = Text::_(self::UNIT_LABELS[$unit] ?? self::UNIT_LABELS['years']);

        if ($date === '') {
            return Text::sprintf('MOD_DINKYMETRICS_FIGURE_SUMMARY_YEARS_SINCE_NO_DATE', $unit);
        }

        return Text::sprintf('MOD_DINKYMETRICS_FIGURE_SUMMARY_YEARS_SINCE', $date, $unit);
    }

    /**
     * @param   Form  $row  The row.
     *
     * @return  string
     */
    private static function contentCount(Form $row): string
    {
        $parts = [self::categoryClause($row, 'category', 'MOD_DINKYMETRICS_FIGURE_CATEGORY_ALL')];

        if (self::string($row, 'include_children', 'yes') === 'yes') {
            $parts[] = Text::_('MOD_DINKYMETRICS_FIGURE_SUMMARY_INCLUDE_CHILDREN');
        }

        $featured = self::string($row, 'featured', 'any');

        if ($featured === 'only') {
            $parts[] = Text::_('MOD_DINKYMETRICS_FIGURE_FEATURED_ONLY');
        } elseif ($featured === 'exclude') {
            $parts[] = Text::_('MOD_DINKYMETRICS_FIGURE_SUMMARY_FEATURED_EXCLUDE');
        }

        $parts[] = self::stateClause($row);

        return self::withPrefixSuffix($row, implode(' · ', array_filter($parts)));
    }

    /**
     * @param   Form  $row  The row.
     *
     * @return  string
     */
    private static function categoryCount(Form $row): string
    {
        $extension = self::string($row, 'extension', 'com_content');
        $parts     = [];

        if ($extension !== 'com_content') {
            $parts[] = Text::_(self::EXTENSION_LABELS[$extension] ?? $extension);
        }

        $parts[] = self::categoryClause($row, 'parent', 'MOD_DINKYMETRICS_FIGURE_PARENT_ALL');

        if (self::string($row, 'include_children_cat', 'no') === 'yes') {
            $parts[] = Text::_('MOD_DINKYMETRICS_FIGURE_SUMMARY_ALL_LEVELS');
        }

        if ($extension === 'com_content' && self::string($row, 'skip_empty', 'no') === 'yes') {
            $parts[] = Text::_('MOD_DINKYMETRICS_FIGURE_SUMMARY_SKIP_EMPTY');
        }

        $parts[] = self::stateClause($row);

        return self::withPrefixSuffix($row, implode(' · ', array_filter($parts)));
    }

    /**
     * The status clause, omitted when it is the default ("published") so the common case
     * does not pad out every summary with the same three words.
     *
     * @param   Form  $row  The row.
     *
     * @return  string
     */
    private static function stateClause(Form $row): string
    {
        $state = self::string($row, 'state', 'published');

        return $state === 'published' ? '' : Text::_(self::STATE_LABELS[$state] ?? '');
    }

    /**
     * "Category X" / "all categories", for whichever field name holds the category id.
     *
     * @param   Form    $row       The row.
     * @param   string  $field     'category' or 'parent'.
     * @param   string  $allKey    Language key for "no category chosen".
     *
     * @return  string
     */
    private static function categoryClause(Form $row, string $field, string $allKey): string
    {
        $id = (int) self::string($row, $field, '0');

        if ($id <= 0) {
            return Text::_($allKey);
        }

        return self::categoryTitle($id) ?? Text::sprintf('MOD_DINKYMETRICS_FIGURE_SUMMARY_UNKNOWN_CATEGORY', $id);
    }

    /**
     * Prepend/append the row's prefix and suffix around an already-built detail string,
     * skipped when both are empty.
     *
     * @param   Form    $row    The row.
     * @param   string  $value  The text to wrap.
     *
     * @return  string
     */
    private static function withPrefixSuffix(Form $row, string $value): string
    {
        $prefix = self::string($row, 'prefix', '');
        $suffix = self::string($row, 'suffix', '');

        if ($prefix === '' && $suffix === '') {
            return $value;
        }

        return trim($prefix . ' ' . $value . $suffix);
    }

    /**
     * A plain category title by id, request-cached since the same category can appear in
     * more than one row.
     *
     * @param   int  $id  The category id.
     *
     * @return  string|null  Null when the category no longer exists.
     */
    private static function categoryTitle(int $id): ?string
    {
        if (\array_key_exists($id, self::$categoryTitles)) {
            return self::$categoryTitles[$id];
        }

        /** @var DatabaseInterface $db */
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('title'))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $title = $db->setQuery($query)->loadResult();

        return self::$categoryTitles[$id] = $title !== null ? (string) $title : null;
    }

    /**
     * A row's stored or default value, defensively cast to string — subform values are
     * whatever type the field last saved.
     *
     * @param   Form    $row      The row.
     * @param   string  $field    The field name.
     * @param   string  $default  Fallback.
     *
     * @return  string
     */
    private static function string(Form $row, string $field, string $default): string
    {
        $value = $row->getValue($field);

        return $value === null || $value === '' ? $default : (string) $value;
    }
}
