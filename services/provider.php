<?php

/**
 * @package     TheLoom.Module
 * @subpackage  Site.DinkyMetrics
 *
 * @copyright   Copyright (C) 2026 The Loom / Stefan Schulz. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://www.the-loom.de
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\Service\Provider\HelperFactory;
use Joomla\CMS\Extension\Service\Provider\Module;
use Joomla\CMS\Extension\Service\Provider\ModuleDispatcherFactory;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     */
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new ModuleDispatcherFactory('\\TheLoom\\Module\\DinkyMetrics'));
        $container->registerServiceProvider(new HelperFactory('\\TheLoom\\Module\\DinkyMetrics\\Site\\Helper'));

        $container->registerServiceProvider(new Module());
    }
};
