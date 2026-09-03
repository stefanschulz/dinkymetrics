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

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The two counting queries behind the content_count and category_count sources.
 *
 * One indexed COUNT(*) per figure, nothing built from user input by string concatenation:
 * every value is bound, every identifier goes through quoteName(), and the enum-like
 * arguments are whitelisted by the caller before they get here.
 *
 * Note that these numbers are deliberately not the same as Joomla's own "Article Count"
 * badge on a category page. That badge counts state = 1 and stops there; a figure that
 * says "1,400 articles" on a public page must not include articles whose publishing has
 * not started, has finished, or that the visitor is not allowed to see.
 */
final class Counter
{
    /**
     * The status filters a figure may ask for. 'any' really means any, trash included.
     */
    public const STATES = ['published', 'published_unpublished', 'any'];

    /**
     * How featured articles are treated.
     */
    public const FEATURED = ['any', 'only', 'exclude'];

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Count articles.
     *
     * @param   int|null       $categoryId       Category to count in, null for the whole site.
     * @param   bool           $includeChildren  Also count articles in descendant categories.
     * @param   string         $state            'published', 'published_unpublished' or 'any'.
     * @param   string         $featured         'any', 'only' or 'exclude'.
     * @param   int[]|null     $viewLevels       Access levels to count, null to ignore access.
     * @param   string|null    $language         Language tag to count (plus '*'), null to ignore.
     * @param   string         $now              Current time as an SQL datetime.
     *
     * @return  int
     */
    public function articles(
        ?int $categoryId,
        bool $includeChildren,
        string $state,
        string $featured,
        ?array $viewLevels,
        ?string $language,
        string $now
    ): int {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__content', 'a'));

        // The category is joined either to walk the tree or to check that the category
        // itself is published (decision E2) — the same join serves both.
        $walkTree      = $categoryId !== null && $includeChildren;
        $needsCategory = $walkTree || $state === 'published';

        if ($needsCategory) {
            $query->innerJoin(
                $db->quoteName('#__categories', 'cc'),
                $db->quoteName('cc.id') . ' = ' . $db->quoteName('a.catid')
            );
        }

        if ($state === 'published') {
            // An article in an unpublished category is not reachable on the site, so it
            // does not belong in a figure that claims to count what is published.
            $query->where($db->quoteName('cc.published') . ' = 1');
        }

        if ($categoryId !== null) {
            if ($includeChildren) {
                $query->innerJoin(
                    $db->quoteName('#__categories', 'root'),
                    $db->quoteName('root.id') . ' = :catid'
                )
                    ->where($db->quoteName('cc.lft') . ' >= ' . $db->quoteName('root.lft'))
                    ->where($db->quoteName('cc.rgt') . ' <= ' . $db->quoteName('root.rgt'))
                    ->bind(':catid', $categoryId, ParameterType::INTEGER);
            } else {
                $query->where($db->quoteName('a.catid') . ' = :catid')
                    ->bind(':catid', $categoryId, ParameterType::INTEGER);
            }
        }

        switch ($state) {
            case 'published':
                $query->where($db->quoteName('a.state') . ' = 1')
                    ->where(
                        '(' . $db->quoteName('a.publish_up') . ' IS NULL OR '
                        . $db->quoteName('a.publish_up') . ' <= :nowUp)'
                    )
                    ->where(
                        '(' . $db->quoteName('a.publish_down') . ' IS NULL OR '
                        . $db->quoteName('a.publish_down') . ' > :nowDown)'
                    )
                    ->bind(':nowUp', $now)
                    ->bind(':nowDown', $now);
                break;

            case 'published_unpublished':
                $query->whereIn($db->quoteName('a.state'), [0, 1]);
                break;

            // 'any' adds no state condition at all — including the trash, as specified.
        }

        if ($featured === 'only') {
            $query->where($db->quoteName('a.featured') . ' = 1');
        } elseif ($featured === 'exclude') {
            $query->where($db->quoteName('a.featured') . ' = 0');
        }

        if ($viewLevels !== null) {
            $query->whereIn($db->quoteName('a.access'), $viewLevels);
        }

        if ($language !== null) {
            $query->whereIn($db->quoteName('a.language'), [$language, '*'], ParameterType::STRING);
        }

        return (int) $db->setQuery($query)->loadResult();
    }

    /**
     * Count categories.
     *
     * @param   string       $extension        Extension the categories belong to.
     * @param   int|null     $parentId         Parent category, null for all of them.
     * @param   bool         $includeChildren  Count every level below the parent, not just the first.
     * @param   string       $state            'published', 'published_unpublished' or 'any'.
     * @param   bool         $skipEmpty        Only count categories holding a published article.
     * @param   int[]|null   $viewLevels       Access levels, applied to $skipEmpty's lookup.
     * @param   string       $now              Current time as an SQL datetime.
     *
     * @return  int
     */
    public function categories(
        string $extension,
        ?int $parentId,
        bool $includeChildren,
        string $state,
        bool $skipEmpty,
        ?array $viewLevels,
        string $now
    ): int {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__categories', 'c'))
            ->where($db->quoteName('c.extension') . ' = :extension')
            ->bind(':extension', $extension, ParameterType::STRING);

        if ($state === 'published') {
            $query->where($db->quoteName('c.published') . ' = 1');
        } elseif ($state === 'published_unpublished') {
            $query->whereIn($db->quoteName('c.published'), [0, 1]);
        }

        if ($parentId !== null) {
            if ($includeChildren) {
                // Strictly inside the parent's bounds, so the parent itself is not counted.
                $query->innerJoin(
                    $db->quoteName('#__categories', 'root'),
                    $db->quoteName('root.id') . ' = :parent'
                )
                    ->where($db->quoteName('c.lft') . ' > ' . $db->quoteName('root.lft'))
                    ->where($db->quoteName('c.rgt') . ' < ' . $db->quoteName('root.rgt'))
                    ->bind(':parent', $parentId, ParameterType::INTEGER);
            } else {
                $query->where($db->quoteName('c.parent_id') . ' = :parent')
                    ->bind(':parent', $parentId, ParameterType::INTEGER);
            }
        }

        if ($skipEmpty) {
            $query->where('EXISTS (' . $this->hasPublishedArticle($viewLevels) . ')')
                ->bind(':nowEmptyUp', $now)
                ->bind(':nowEmptyDown', $now);
        }

        return (int) $db->setQuery($query)->loadResult();
    }

    /**
     * The correlated subquery behind skip_empty — one query for all categories rather
     * than one lookup per row.
     *
     * The access levels are cast to int and inlined instead of bound: a subquery's own
     * bound parameters are lost the moment it is turned into a string and embedded, and
     * the values come from getAuthorisedViewLevels(), which returns integers.
     *
     * @param   int[]|null  $viewLevels  Access levels to honour, or null for all of them.
     *
     * @return  string
     */
    private function hasPublishedArticle(?array $viewLevels): string
    {
        $db  = $this->db;
        $sub = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__content', 'ct'))
            ->where($db->quoteName('ct.catid') . ' = ' . $db->quoteName('c.id'))
            ->where($db->quoteName('ct.state') . ' = 1')
            ->where(
                '(' . $db->quoteName('ct.publish_up') . ' IS NULL OR '
                . $db->quoteName('ct.publish_up') . ' <= :nowEmptyUp)'
            )
            ->where(
                '(' . $db->quoteName('ct.publish_down') . ' IS NULL OR '
                . $db->quoteName('ct.publish_down') . ' > :nowEmptyDown)'
            );

        if ($viewLevels !== null) {
            $levels = implode(',', array_map('intval', $viewLevels)) ?: '0';
            $sub->where($db->quoteName('ct.access') . ' IN (' . $levels . ')');
        }

        return (string) $sub;
    }
}
