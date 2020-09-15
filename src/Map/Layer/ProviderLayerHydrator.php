<?php

declare(strict_types=1);

namespace Cowegis\Bundle\ContaoProviderLayer\Map\Layer;

use Cowegis\Bundle\Contao\Hydrator\Layer\LayerTypeHydrator;
use Cowegis\Bundle\Contao\Model\LayerModel;
use Cowegis\Bundle\Contao\Provider\MapLayerContext;
use Cowegis\Core\Definition\Layer\Layer;

final class ProviderLayerHydrator extends LayerTypeHydrator
{
    /** @var array */
    private $configuration;

    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
    }

    protected function hydrateLayer(LayerModel $layerModel, Layer $layer, MapLayerContext $context) : void
    {
        $options = $this->configuration[$layerModel->tile_provider]['options'] ?? [];
        foreach ($options as $target => $source) {
            $layer->options()->set($target, $layerModel->$source);
        }
    }

    protected function supportedType() : string
    {
        return 'provider';
    }
}
