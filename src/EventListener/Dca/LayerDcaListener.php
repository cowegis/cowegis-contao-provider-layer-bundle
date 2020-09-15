<?php

declare(strict_types=1);

namespace Cowegis\Bundle\ContaoProviderLayer\EventListener\Dca;

use Contao\DataContainer;
use Netzmacht\Contao\Toolkit\Dca\Listener\AbstractListener;
use Netzmacht\Contao\Toolkit\Dca\Manager;
use function array_keys;

final class LayerDcaListener extends AbstractListener
{
    protected static $name = 'tl_cowegis_layer';

    /** @var array */
    private $configuration;

    public function __construct(Manager $dcaManager, array $configuration)
    {
        parent::__construct($dcaManager);

        $this->configuration = $configuration;
    }

    public function providerOptions() : array
    {
        return array_keys($this->configuration);
    }

    public function variantOptions(DataContainer $dataContainer) : array
    {
        if ($dataContainer->activeRecord && $dataContainer->activeRecord->tile_provider) {
            return $this->configuration[$dataContainer->activeRecord->tile_provider]['variants'] ?? [];
        }

        return [];
    }
}
