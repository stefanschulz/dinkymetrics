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

use Joomla\CMS\Form\Field\SubformField;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The Figures subform, rendered as a one-line summary per row instead of every field
 * spelled out — the tabular and plain repeatable layouts both become unreadable past a
 * handful of rows, each with sixteen fields.
 *
 * Everything about *editing and reordering a row* is untouched SubformField/
 * joomla-field-subform behaviour: add, remove and the built-in drag-to-reorder (with an
 * up/down fallback) all keep working exactly as on any other subform, because the layout
 * only changes how a row is *displayed*, not the markup the web component depends on
 * (same `.subform-repeatable-group` wrapper, same `.group-add`/`.group-remove`/
 * `.group-move` button classes). Only getLayoutPaths() is overridden, to let the layout
 * ship inside this module instead of Joomla's global layouts/ folder.
 *
 * Resolved by Joomla via the `addfieldprefix` on <config> in mod_dinkymetrics.xml, which
 * maps type="dinkyfigures" to this class (FormHelper::loadClass()); no field/ folder
 * convention needed since it is found through the extension's own PSR-4 namespace.
 */
class DinkyfiguresField extends SubformField
{
    protected $layout = 'field.subform.dinkymetrics';

    /**
     * Prepend this module's own layouts/ folder, ahead of Joomla's global one but behind
     * any administrator template override — the same priority order template overrides
     * get everywhere else.
     *
     * @return  string[]
     */
    protected function getLayoutPaths()
    {
        $paths = parent::getLayoutPaths();

        array_splice($paths, \count($paths) - 1, 0, [JPATH_ROOT . '/modules/mod_dinkymetrics/layouts']);

        return $paths;
    }
}
