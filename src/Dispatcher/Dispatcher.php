<?php

/**
 * @package     TheLoom.Module
 * @subpackage  Site.DinkyMetrics
 *
 * @copyright   Copyright (C) 2026 The Loom / Stefan Schulz. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://www.the-loom.de
 */

namespace TheLoom\Module\DinkyMetrics\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\CMS\Helper\ModuleHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Resolves the figures and hands them to the layout.
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Assemble the data the layout renders.
     *
     * @return  array
     */
    protected function getLayoutData(): array
    {
        $data   = parent::getLayoutData();
        $params = $data['params'];
        $app    = $data['app'];

        $cacheParams               = new \stdClass();
        $cacheParams->cachemode    = 'id';
        $cacheParams->class        = $this->getHelperFactory()->getHelper('DinkyMetricsHelper');
        $cacheParams->method       = 'getFigures';
        $cacheParams->methodparams = [$params, $app];
        $cacheParams->modeparams   = $this->cacheKey($params, $app);

        $data['figures'] = ModuleHelper::moduleCache($this->module, $params, $cacheParams);

        return $data;
    }

    /**
     * Everything the figures depend on, as one cache key.
     *
     * The specification asks for cachemode "static" when access is ignored and "itemid"
     * otherwise, but "itemid" keys on the menu item, not on who is looking: two visitors
     * with different access levels would be served each other's numbers. So the mode is
     * "id" throughout and the key is built here, the way mod_articles does it — same
     * intent, correct in both configurations. See decision E1 in .doc/WORKPLAN.md.
     *
     * @param   \Joomla\Registry\Registry             $params  The module parameters.
     * @param   \Joomla\CMS\Application\CMSApplication $app    The application.
     *
     * @return  string
     */
    private function cacheKey($params, $app): string
    {
        $parts = [
            $this->module->id,
            $this->module->module,
            (string) $params,
            $app->getInput()->getInt('Itemid'),
            $app->getLanguage()->getTag(),
        ];

        // Only user-dependent when the access filter is actually on; with it off every
        // visitor gets the same numbers and the same cache entry.
        if ($params->get('count_all_access', 'no') !== 'yes') {
            $parts[] = $app->getIdentity()->getAuthorisedViewLevels();
        }

        return md5(serialize($parts));
    }
}
