<?php

declare(strict_types=1);

namespace Cowegis\Bundle\ContaoProviderLayer\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class CowegisContaoProviderLayerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container) : void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $loader->load('config.xml');
        $loader->load('services.xml');
    }
}
