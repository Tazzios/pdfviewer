<?php
defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Factory;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Tazzios\Plugin\Content\Pdfviewer\Extension\Pdfviewer;

return new class implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                return new Pdfviewer(
                    $container->get('dispatcher'),
                    (array) PluginHelper::getPlugin('content', 'pdfviewer'),
                    Factory::getApplication(),
                    
                );
            }
        );
    }
};
