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

/**
 * @psalm-type TProviderConfig = array{
 *     url: string,
 *     variants?: list<string>|array<string,array<string,string>>,
 *     options?: array<string,string>,
 *     fields?: list<string>
 * }
 */
final class ProviderLayerType implements LayerType
{
    use MapLayerType;

    /** @param array<string,TProviderConfig> $configuration */
    public function __construct(private readonly TranslatorInterface $translator, private readonly array $configuration)
    {
    }

    public function name(): string
    {
        return 'provider';
    }

    public function createDefinition(LayerModel $layerModel, MapLayerModel $mapLayerModel): Layer
    {
        $variants = $this->configuration[$layerModel->tile_provider]['variants'] ?? [];
        $variant  = null;

        if ($variants) {
            /** @psalm-suppress UndefinedMagicPropertyFetch */
            $variant = $layerModel->tile_provider_variant;
            if (! isset($variants[$variant]) && ! in_array($variant, $variants, true)) {
                $variant = null;
            }
        }

        return new ProviderLayer(
            $layerModel->layerId(),
            $this->hydrateName($layerModel, $mapLayerModel),
            (string) $layerModel->tile_provider,
            $variant,
            $this->hydrateInitialVisible($mapLayerModel),
        );
    }

    /** @param array<string,mixed> $row */
    public function label(string $label, array $row): string
    {
        $langKey    = 'leaflet_provider.' . $row['tile_provider'] . '.0';
        $translated = $this->translator->trans($langKey, [], 'contao_leaflet');

        if ($translated !== $langKey) {
            $provider = $translated;
        } else {
            $provider = $row['tile_provider'];
        }

        $variant = isset($this->configuration[$row['tile_provider']]['variants'])
            ? $row['tile_provider_variant']
            : null;

        if ($variant !== null) {
            $label .= sprintf('<span class="tl_gray"> (%s, %s)</span>', $provider, $variant);

            return $label;
        }

        $label .= sprintf('<span class="tl_gray"> (%s)</span>', $provider);

        return $label;
    }
}
