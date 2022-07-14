<?php

declare(strict_types=1);

namespace Cowegis\Bundle\ContaoProviderLayer\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Cowegis\Bundle\Contao\CowegisContaoBundle;
use Cowegis\Bundle\ContaoProviderLayer\CowegisContaoProviderLayerBundle;

final class Plugin implements BundlePluginInterface
{
    /**
     * {@inheritDoc}
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(CowegisContaoProviderLayerBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class, CowegisContaoBundle::class]),
        ];
    }
}
