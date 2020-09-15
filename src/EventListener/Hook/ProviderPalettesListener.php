<?php

declare(strict_types=1);

namespace Cowegis\Bundle\ContaoProviderLayer\EventListener\Hook;

use Cowegis\Bundle\Contao\Model\LayerModel;
use Netzmacht\Contao\Toolkit\Dca\Manager as DcaManager;

final class ProviderPalettesListener
{
    /** @var DcaManager */
    private $dcaManager;

    /** @var array */
    private $configuration;

    public function __construct(DcaManager $dcaManager, array $configuration)
    {
        $this->dcaManager    = $dcaManager;
        $this->configuration = $configuration;
    }

    public function onLoadDataContainer(string $dataContainerName) : void
    {
        if ($dataContainerName !== LayerModel::getTable()) {
            return;
        }

        $definition = $this->dcaManager->getDefinition($dataContainerName);

        foreach ($this->configuration as $name => $configuration) {
            if (!isset($configuration['fields'])) {
                continue;
            }

            $definition->set(['metasubselectpalettes', 'tile_provider', $name], $configuration['fields']);
        }
    }
}
