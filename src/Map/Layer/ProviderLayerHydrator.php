<?php

declare(strict_types=1);

namespace Cowegis\Bundle\ContaoProviderLayer\Map\Layer;

use Cowegis\Bundle\Contao\Hydrator\Hydrator;
use Cowegis\Bundle\Contao\Map\Layer\LayerTypeHydrator;
use Cowegis\Bundle\Contao\Model\LayerModel;
use Cowegis\Bundle\Contao\Provider\MapLayerContext;
use Cowegis\Core\Definition\Layer\Layer;
use Netzmacht\Contao\Toolkit\Response\ResponseTagger;

/**
 * @psalm-import-type TProviderConfig from ProviderLayerType
 */
final class ProviderLayerHydrator extends LayerTypeHydrator
{
    /** @var array<string,TProviderConfig> */
    private array $configuration;

    /** @param array<string,TProviderConfig> $configuration */
    public function __construct(array $configuration, ResponseTagger $responseTagger)
    {
        parent::__construct($responseTagger);

        $this->configuration = $configuration;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function hydrateLayer(
        LayerModel $layerModel,
        Layer $layer,
        MapLayerContext $context,
        Hydrator $hydrator
    ): void {
        $options = $this->configuration[$layerModel->tile_provider]['options'] ?? [];
        foreach ($options as $target => $source) {
            $layer->options()->set($target, $layerModel->$source);
        }
    }

    protected function supportedType(): string
    {
        return 'provider';
    }
}
