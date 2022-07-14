<?php

declare(strict_types=1);

namespace Cowegis\Bundle\ContaoProviderLayer\EventListener\Hook;

use Cowegis\Bundle\Contao\Model\LayerModel;
use Cowegis\Bundle\ContaoProviderLayer\Map\Layer\ProviderLayerType;
use Netzmacht\Contao\Toolkit\Dca\Manager as DcaManager;

/**
 * @psalm-import-type TProviderConfig from ProviderLayerType
 */
final class ProviderPalettesListener
{
    private DcaManager $dcaManager;

    /** @var array<string,TProviderConfig> */
    private array $configuration;

    /** @param array<string,TProviderConfig> $configuration */
    public function __construct(DcaManager $dcaManager, array $configuration)
    {
        $this->dcaManager    = $dcaManager;
        $this->configuration = $configuration;
    }

    public function onLoadDataContainer(string $dataContainerName): void
    {
        if ($dataContainerName !== LayerModel::getTable()) {
            return;
        }

        $definition = $this->dcaManager->getDefinition($dataContainerName);

        foreach ($this->configuration as $name => $configuration) {
            if (! isset($configuration['fields'])) {
                continue;
            }

            $definition->set(['metasubselectpalettes', 'tile_provider', $name], $configuration['fields']);
        }
    }
}
