<?php

/**
 * @package     TheLoom.Module
 * @subpackage  Site.DinkyMetrics
 *
 * @copyright   Copyright (C) 2026 The Loom / Stefan Schulz. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://www.the-loom.de
 *
 * The Figures subform wrapper. A close copy of Joomla's own
 * layouts/joomla/form/field/subform/repeatable.php — same <joomla-field-subform> markup
 * and button wiring, so add/remove/drag-reorder are untouched core behaviour — except
 * each row renders through this module's own "row" sublayout (collapsed summary, details
 * expand for the full fields) instead of core's "section" (every field always visible).
 *
 * @var  Form    $tmpl      The empty template form, used to render a fresh row.
 * @var  array   $forms     One Form instance per existing row.
 * @var  bool    $multiple  Always true here — the field always allows more than one row.
 * @var  string  $name      Input name.
 * @var  string  $fieldname The field name.
 * @var  string  $control   The form control prefix.
 * @var  string  $class     Extra classes for the container.
 * @var  array   $buttons   Which of add/remove/move are enabled.
 * @var  int     $min       Minimum row count.
 * @var  int     $max       Maximum row count.
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;

extract($displayData);

if ($multiple) {
    $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
    $wa->useScript('webcomponent.field-subform');

    // Not loaded automatically — see the same call in tmpl/default.php.
    $wa->getRegistry()->addExtensionRegistryFile('mod_dinkymetrics');
    $wa->useStyle('mod_dinkymetrics.admin-figures');
    $wa->useScript('mod_dinkymetrics.admin-figures');
}

$class = $class ? ' ' . $class : '';
?>
<div class="subform-repeatable-wrapper subform-layout dinkymetrics-figures">
    <joomla-field-subform class="subform-repeatable<?php echo $class; ?>" name="<?php echo $name; ?>"
        button-add=".group-add" button-remove=".group-remove" button-move="<?php echo empty($buttons['move']) ? '' : '.group-move'; ?>"
        repeatable-element=".subform-repeatable-group" minimum="<?php echo $min; ?>" maximum="<?php echo $max; ?>">
        <?php if (!empty($buttons['add'])) : ?>
        <div class="btn-toolbar">
            <div class="btn-group">
                <button type="button" class="group-add btn btn-sm button btn-success" aria-label="<?php echo Text::_('JGLOBAL_FIELD_ADD'); ?>">
                    <span class="icon-plus icon-white" aria-hidden="true"></span>
                    <?php echo Text::_('MOD_DINKYMETRICS_FIGURE_ADD'); ?>
                </button>
            </div>
        </div>
        <?php endif; ?>
    <?php
    foreach ($forms as $k => $form) :
        echo $this->sublayout('row', [
            'form' => $form, 'basegroup' => $fieldname, 'group' => $fieldname . $k,
            'buttons' => $buttons, 'isTemplate' => false,
        ]);
    endforeach;
    ?>
    <template class="subform-repeatable-template-section hidden"><?php
        // The freshly-cloned row starts expanded: it is empty, so there is nothing yet for
        // a one-line summary to say, and the editor is about to fill it in anyway.
        echo trim($this->sublayout('row', [
            'form' => $tmpl, 'basegroup' => $fieldname, 'group' => $fieldname . 'X',
            'buttons' => $buttons, 'isTemplate' => true,
        ]));
        ?></template>
    </joomla-field-subform>
</div>
