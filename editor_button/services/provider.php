<?php

/**
 * @copyright  (C) 2023 Tazzios
 * @license    GNU General Public License version 3 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Tazzios\Plugin\EditorsXtd\Pdfviewer\Extension\Pdfviewer;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            static function (Container $container) {
                $config = (array) PluginHelper::getPlugin('editors-xtd', 'pdfviewer');

                // Joomla 5 expects the dispatcher as first argument, Joomla 6 only the config.
                if (version_compare(JVERSION, '6.0', '<')) {
                    $plugin = new Pdfviewer($container->get(DispatcherInterface::class), $config);
                } else {
                    $plugin = new Pdfviewer($config);
                }

                $plugin->setApplication(Factory::getApplication());
                $plugin->setDatabase($container->get(DatabaseInterface::class));

                return $plugin;
            }
        );
    }
};
