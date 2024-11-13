<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Task.YandexTurbo
 *
 * @copyright   (C) 2024 Sergey Kuznetsov. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Task\YandexTurbo\Extension\YandexTurbo;

return new class() implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   1.1.0
     */
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $plugin = new YandexTurbo(
                    $container->get(DispatcherInterface::class),
                    (array) PluginHelper::getPlugin('task', 'yandexturbo'),
                    JPATH_ROOT . '/media/',
                    'https://' . Uri::getInstance()->getHost()
                );
                $plugin->setApplication(Factory::getApplication());
                return $plugin;
            }
        );
    }
};
