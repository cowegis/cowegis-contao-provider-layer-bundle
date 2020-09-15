<?php

declare(strict_types=1);

namespace Cowegis\Bundle\ContaoProviderLayer\Map\Layer;

use Cowegis\Bundle\Contao\Map\Layer\LayerType;
use Cowegis\Bundle\Contao\Map\Layer\MapLayerType;
use Cowegis\Bundle\Contao\Model\LayerModel;
use Cowegis\Bundle\Contao\Model\Map\MapLayerModel;
use Cowegis\Core\Definition\Layer\Layer;
use Cowegis\Core\Definition\Layer\ProviderLayer;
use Symfony\Contracts\Translation\TranslatorInterface;
use function in_array;
use function sprintf;

final class ProviderLayerType implements LayerType
{
    use MapLayerType;

    /** @var TranslatorInterface */
    private $translator;

    /** @var array */
    private $configuration;

    public function __construct(TranslatorInterface $translator, array $configuration)
    {
        $this->translator    = $translator;
        $this->configuration = $configuration;
    }

    public function name() : string
    {
        return 'provider';
    }

    public function createDefinition(LayerModel $layerModel, MapLayerModel $mapLayerModel) : Layer
    {
        $variants = $this->configuration[$layerModel->tile_provider]['variants'] ?? [];
        $variant  = in_array($layerModel->tile_provider_variant, $variants) ? $layerModel->tile_provider_variant : null;

        return new ProviderLayer(
            $layerModel->layerId(),
            $this->hydrateName($layerModel, $mapLayerModel),
            $layerModel->tile_provider,
            $variant,
            $this->hydrateInitialVisible($mapLayerModel)
        );
    }

    public function label(string $label, array $row) : string
    {
        $langKey    = 'leaflet_provider.' . $row['tile_provider'] . '.0';
        $translated = $this->translator->trans($langKey, [], 'contao_leaflet');

        if ($translated !== $langKey) {
            $provider = $translated;
        } else {
            $provider = $row['tile_provider'];
        }

        $label .= sprintf('<span class="tl_gray"> (%s)</span>', $provider);

        return $label;
    }
}
