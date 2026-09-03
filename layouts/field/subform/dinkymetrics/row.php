<?php

/**
 * @package     TheLoom.Module
 * @subpackage  Site.DinkyMetrics
 *
 * @copyright   Copyright (C) 2026 The Loom / Stefan Schulz. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://www.the-loom.de
 *
 * One figure row: a collapsed one-line summary that expands to the full fields.
 *
 * The outer div keeps exactly the markup joomla-field-subform.js looks for
 * (.subform-repeatable-group, data-base-name, data-group) — that is what makes add,
 * remove and the built-in drag-to-reorder work without a line of extra JS here. The
 * move/remove/add buttons sit outside the <details> so a row can be reordered or deleted
 * without opening it; only the field body is inside the disclosure.
 *
 * @var  Form    $form        This row's Form instance.
 * @var  string  $basegroup   The field's base name.
 * @var  string  $group       This row's group name (e.g. "figures0", or "figuresX" for
 *                            the hidden template row cloned when "Add" is pressed).
 * @var  array   $buttons     Which of add/remove/move are enabled.
 * @var  bool    $isTemplate  True for the hidden template row.
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use TheLoom\Module\DinkyMetrics\Site\Field\FigureSummary;

extract($displayData);

$summary = FigureSummary::summarise($form);
$linked  = trim((string) $form->getValue('link')) !== '';
?>
<div class="subform-repeatable-group dinkymetrics-figure" data-base-name="<?php echo $basegroup; ?>" data-group="<?php echo $group; ?>">
    <div class="dinkymetrics-figure__row">
        <details class="dinkymetrics-figure__details"<?php echo $isTemplate ? ' open' : ''; ?>>
            <summary class="dinkymetrics-figure__summary">
                <span class="dinkymetrics-figure__caption"><?php echo $this->escape($summary['caption']); ?></span>
                <span class="dinkymetrics-figure__source"><?php echo $this->escape($summary['source']); ?></span>
                <?php if ($summary['detail'] !== '') : ?>
                    <span class="dinkymetrics-figure__detail"><?php echo $this->escape($summary['detail']); ?></span>
                <?php endif; ?>
                <?php if ($linked) : ?>
                    <span class="dinkymetrics-figure__badge" title="<?php echo $this->escape(Text::_('MOD_DINKYMETRICS_FIGURE_LINK_LABEL')); ?>">
                        <span class="icon-link" aria-hidden="true"></span>
                    </span>
                <?php endif; ?>
            </summary>
            <div class="dinkymetrics-figure__body">
                <?php foreach ($form->getGroup('') as $field) : ?>
                    <?php echo $field->renderField(); ?>
                <?php endforeach; ?>
            </div>
        </details>
        <?php if (!empty($buttons)) : ?>
        <div class="dinkymetrics-figure__toolbar btn-toolbar">
            <div class="btn-group">
                <?php if (!empty($buttons['add'])) : ?>
                    <button type="button" class="group-add btn btn-sm btn-success"
                        aria-label="<?php echo Text::_('JGLOBAL_FIELD_ADD'); ?>">
                        <span class="icon-plus icon-white" aria-hidden="true"></span>
                    </button>
                <?php endif; ?>
                <?php if (!empty($buttons['remove'])) : ?>
                    <button type="button" class="group-remove btn btn-sm btn-danger"
                        aria-label="<?php echo Text::_('JGLOBAL_FIELD_REMOVE'); ?>">
                        <span class="icon-minus icon-white" aria-hidden="true"></span>
                    </button>
                <?php endif; ?>
                <?php if (!empty($buttons['move'])) : ?>
                    <button type="button" class="group-move btn btn-sm btn-primary"
                        aria-label="<?php echo Text::_('JGLOBAL_FIELD_MOVE'); ?>">
                        <span class="icon-arrows-alt icon-white" aria-hidden="true"></span>
                    </button>
                    <button type="button" class="group-move-up btn btn-sm"
                        aria-label="<?php echo Text::_('JGLOBAL_FIELD_MOVE_UP'); ?>">
                        <span class="icon-chevron-up" aria-hidden="true"></span>
                    </button>
                    <button type="button" class="group-move-down btn btn-sm"
                        aria-label="<?php echo Text::_('JGLOBAL_FIELD_MOVE_DOWN'); ?>">
                        <span class="icon-chevron-down" aria-hidden="true"></span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
