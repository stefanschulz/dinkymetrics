<?php

/**
 * @package     TheLoom.Module
 * @subpackage  Site.DinkyMetrics
 *
 * @copyright   Copyright (C) 2026 The Loom / Stefan Schulz. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://www.the-loom.de
 *
 * The markup contract. A site that wants different markup copies this file to
 * templates/<template>/html/mod_dinkymetrics/default.php rather than fighting the CSS.
 *
 * Two rules hold the contract together:
 *
 * - The finished, formatted number is always in the HTML. Search engines, screen readers
 *   and visitors without JavaScript see the real figure; the animation only counts up to
 *   a value that is already there.
 * - The animation writes to .mod-dinkymetrics__number and nothing else. Prefix and suffix
 *   live in their own elements so they are never touched, and the whole figure is spelled
 *   out once in aria-label so assistive technology reads it deterministically.
 *
 * @var  array                                   $figures  Resolved figures from the helper.
 * @var  \Joomla\Registry\Registry               $params   Module parameters.
 * @var  \Joomla\CMS\Application\SiteApplication $app      The application.
 * @var  \stdClass                               $module   The module record.
 */

\defined('_JEXEC') or die;

// No figures at all: render nothing, not even a wrapper. The usual module chrome bows
// out when the content is empty, so the module leaves no trace on the page.
if (empty($figures)) {
    return;
}

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$animate  = $params->get('animate', 'yes') !== 'no';
$decimals = max(0, (int) $params->get('format_decimals', 0));
$grouping = $params->get('format_grouping', 'yes') !== 'no';
$locale   = \TheLoom\Module\DinkyMetrics\Site\Helper\Formatter::normaliseLocale(
    (string) $params->get('format_locale', ''),
    $app->getLanguage()->getTag()
);

$wa = $app->getDocument()->getWebAssetManager();

// An extension's joomla.asset.json is not read automatically — without this the two
// asset names below simply do not exist.
$wa->getRegistry()->addExtensionRegistryFile('mod_dinkymetrics');
$wa->useStyle('mod_dinkymetrics.style');

if ($animate) {
    $wa->useScript('mod_dinkymetrics.script');
}

// The raw value the animation counts to, rounded exactly as the server rounded it, with
// a dot for a decimal mark and no grouping — so the tween ends on the string already in
// the page instead of drifting a digit away from it.
$raw = static fn ($value): string => number_format((float) $value, $decimals, '.', '');

$classes = 'mod-dinkymetrics' . $escape($params->get('moduleclass_sfx', ''));
?>
<div class="<?php echo $classes; ?>"
    <?php if ($animate) : ?>
     data-trigger="<?php echo $escape($params->get('animate_trigger', 'in-view')); ?>"
     data-duration="<?php echo (int) $params->get('animate_duration', 1600); ?>"
     data-easing="<?php echo $escape($params->get('animate_easing', 'ease-out')); ?>"
     data-threshold="<?php echo $escape($params->get('animate_threshold', 0.35)); ?>"
     data-locale="<?php echo $escape($locale); ?>"
    <?php endif; ?>>
    <ul class="mod-dinkymetrics__list" role="list">
        <?php foreach ($figures as $figure) : ?>
            <li class="mod-dinkymetrics__item">
                <?php if ($figure['href'] !== null) : ?>
                    <a class="mod-dinkymetrics__link" href="<?php echo $escape($figure['href']); ?>"
                        <?php echo $figure['external'] ? ' rel="noopener noreferrer"' : ''; ?>>
                <?php endif; ?>
                <span class="mod-dinkymetrics__value"
                      data-target="<?php echo $escape($raw($figure['raw'])); ?>"
                      data-from="<?php echo $escape($raw($params->get('animate_from', 0))); ?>"
                      data-decimals="<?php echo $decimals; ?>"
                      data-grouping="<?php echo $grouping ? '1' : '0'; ?>"
                      data-prefix="<?php echo $escape($figure['prefix']); ?>"
                      data-suffix="<?php echo $escape($figure['suffix']); ?>"
                      aria-label="<?php echo $escape($figure['aria']); ?>">
                    <?php // No whitespace between these three, or the number gets spaced out. ?>
                    <span class="mod-dinkymetrics__prefix" aria-hidden="true"><?php echo $escape($figure['prefix']); ?></span><!--
                 --><span class="mod-dinkymetrics__number"><?php echo $escape($figure['formatted']); ?></span><!--
                 --><span class="mod-dinkymetrics__suffix" aria-hidden="true"><?php echo $escape($figure['suffix']); ?></span>
                </span>
                <span class="mod-dinkymetrics__label"><?php echo $escape($figure['label']); ?></span>
                <?php if ($figure['href'] !== null) : ?>
                    </a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php if ((int) $params->get('debug', 0) === 1) : ?>
<!-- mod_dinkymetrics #<?php echo (int) $module->id; ?>: <?php echo \count($figures); ?> figure(s),
     animation <?php echo $animate ? 'on' : 'off'; ?> -->
<?php endif; ?>
