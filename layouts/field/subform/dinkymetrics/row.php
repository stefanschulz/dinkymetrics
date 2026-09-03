<?php

/**
 * @package     TheLoom.Module
 * @subpackage  Site.DinkyMetrics
 *
 * @copyright   Copyright (C) 2026 The Loom / Stefan Schulz. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://www.the-loom.de
 *
 * One figure row: a single compact line — drag handle, summary, edit, delete — with the
 * full field set in a Bootstrap modal instead of an inline expand. Matches how the rest
 * of the Joomla admin already opens things ("Select Category" and the media field both
 * use the same modal-fade/data-bs-toggle pattern), which a bespoke inline editor would
 * not.
 *
 * The outer div keeps exactly the markup joomla-field-subform.js looks for
 * (.subform-repeatable-group, data-base-name, data-group) — that is what makes drag-to-
 * reorder and delete work without a line of extra JS here: .group-move is the drag
 * handle, .group-move-up/-down the keyboard/screen-reader-reachable fallback for the
 * same reordering, .group-remove the trash button. Deliberately no per-row add button —
 * the one in the wrapper's toolbar is the only way to add a row — see .doc/WORKPLAN.md.
 *
 * The modal's id is set from $group ("figures0", …) here for the server-rendered rows.
 * A row cloned client-side from the hidden <template> (data-group="figuresX" in the raw
 * markup) gets a fresh one from media/mod_dinkymetrics/js/admin-figures.js once Joomla's
 * own clone logic has assigned it a real group name — that logic only touches elements
 * carrying a `name` attribute, which this id is not, so it never happens on its own.
 *
 * @var  Form    $form       This row's Form instance.
 * @var  string  $basegroup  The field's base name.
 * @var  string  $group      This row's group name (e.g. "figures0", or "figuresX" for the
 *                           hidden template row cloned when "Add" is pressed).
 * @var  array   $buttons    Which of remove/move are enabled.
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use TheLoom\Module\DinkyMetrics\Site\Field\FigureSummary;

extract($displayData);

$summary  = FigureSummary::summarise($form);
$linked   = trim((string) $form->getValue('link')) !== '';
$modalId  = 'dm-modal-' . $group;
$titleId  = $modalId . '-title';
?>
<div class="subform-repeatable-group dinkymetrics-figure" data-base-name="<?php echo $basegroup; ?>" data-group="<?php echo $group; ?>">
    <div class="dinkymetrics-figure__row border rounded bg-body-tertiary">
        <?php if (!empty($buttons['move'])) : ?>
            <button type="button" class="group-move dinkymetrics-figure__handle" aria-label="<?php echo Text::_('JGLOBAL_FIELD_MOVE'); ?>">
                <span class="icon-arrows-alt" aria-hidden="true"></span>
            </button>
        <?php endif; ?>
        <div class="dinkymetrics-figure__info">
            <span class="dinkymetrics-figure__caption"><?php echo $this->escape($summary['caption']); ?></span>
            <span class="dinkymetrics-figure__source text-body-secondary"><?php echo $this->escape($summary['source']); ?></span>
            <?php if ($summary['detail'] !== '') : ?>
                <span class="dinkymetrics-figure__detail text-body-secondary"><?php echo $this->escape($summary['detail']); ?></span>
            <?php endif; ?>
            <?php if ($linked) : ?>
                <span class="dinkymetrics-figure__badge text-body-secondary"
                    title="<?php echo $this->escape(Text::_('MOD_DINKYMETRICS_FIGURE_LINK_LABEL')); ?>">
                    <span class="icon-link" aria-hidden="true"></span>
                </span>
            <?php endif; ?>
        </div>
        <div class="dinkymetrics-figure__actions">
            <?php if (!empty($buttons['move'])) : ?>
                <button type="button" class="group-move-up dinkymetrics-figure__reorder" aria-label="<?php echo Text::_('JGLOBAL_FIELD_MOVE_UP'); ?>">
                    <span class="icon-chevron-up" aria-hidden="true"></span>
                </button>
                <button type="button" class="group-move-down dinkymetrics-figure__reorder" aria-label="<?php echo Text::_('JGLOBAL_FIELD_MOVE_DOWN'); ?>">
                    <span class="icon-chevron-down" aria-hidden="true"></span>
                </button>
            <?php endif; ?>
            <button type="button" class="dinkymetrics-figure__edit" data-bs-toggle="modal" data-bs-target="#<?php echo $modalId; ?>"
                aria-label="<?php echo Text::_('MOD_DINKYMETRICS_FIGURE_EDIT'); ?>">
                <span class="icon-pencil" aria-hidden="true"></span>
            </button>
            <?php if (!empty($buttons['remove'])) : ?>
                <button type="button" class="group-remove dinkymetrics-figure__delete" aria-label="<?php echo Text::_('JGLOBAL_FIELD_REMOVE'); ?>">
                    <span class="icon-trash" aria-hidden="true"></span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal fade dinkymetrics-figure__modal" id="<?php echo $modalId; ?>" tabindex="-1" aria-labelledby="<?php echo $titleId; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title" id="<?php echo $titleId; ?>"><?php echo $this->escape($summary['caption']); ?></h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
                </div>
                <div class="modal-body form-vertical">
                    <?php // form-vertical is what makes Joomla's own .control-group stack label-above-field
                    // (".form-vertical .control-group{flex-direction:column}"); the rest of this edit
                    // page has it on the surrounding <form>, a modal does not inherit from there. ?>
                    <?php foreach ($form->getGroup('') as $field) : ?>
                        <?php echo $field->renderField(); ?>
                    <?php endforeach; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Text::_('JCLOSE'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
