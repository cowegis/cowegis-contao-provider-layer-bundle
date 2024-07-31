<?php

declare(strict_types=1);

namespace Cowegis\Bundle\ContaoProviderLayer\EventListener\Dca;

use Contao\BackendTemplate;
use Contao\CoreBundle\Framework\Adapter;
use Contao\DataContainer;
use Contao\Model;
use Cowegis\Bundle\Contao\Model\LayerModel;
use Cowegis\Bundle\Contao\Model\LayerRepository;
use Cowegis\Bundle\ContaoProviderLayer\Map\Layer\ProviderLayerType;
use Netzmacht\Contao\Toolkit\Dca\Listener\AbstractListener;
use Netzmacht\Contao\Toolkit\Dca\Manager;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_keys;
use function in_array;
use function is_string;

/** @psalm-import-type TProviderConfig from ProviderLayerType */
final class LayerDcaListener extends AbstractListener
{
    /** @param array<string,TProviderConfig> $configuration */
    public function __construct(
        Manager $dcaManager,
        private readonly TranslatorInterface $translator,
        private readonly Adapter $inputAdapter,
        private readonly LayerRepository $layerRepository,
        private readonly array $configuration,
    ) {
        parent::__construct($dcaManager);
    }

    public static function getName(): string
    {
        return 'tl_cowegis_layer';
    }

    public function initialize(DataContainer $dataContainer): void
    {
        if ($this->inputAdapter->get('act') !== 'edit') {
            return;
        }

        $layer = $this->layerRepository->find((int) $dataContainer->id);
        if (! $layer instanceof Model) {
            return;
        }

        $provider = $layer->tile_provider;
        if ($this->inputAdapter->post('FORM_SUBMIT') === LayerModel::getTable()) {
            $provider = $this->inputAdapter->post('tile_provider');
        }

        if (! isset($this->configuration[$provider]['variants'])) {
            $this->getDefinition()->set(['fields', 'tile_provider_variant', 'exclude'], true);

            return;
        }

        $variants = $this->configuration[$provider]['variants'];
        $variant  = $this->inputAdapter->post('tile_provider_variant');
        if (isset($variants[$variant]) || in_array($variant, $variants, true)) {
            return;
        }

        $keys  = array_keys($variants);
        $first = is_string($keys[0]) ? $keys[0] : $variants[$keys[0]];
        $this->inputAdapter->setPost('tile_provider_variant', $first);
    }

    /** @return list<string> */
    public function providerOptions(): array
    {
        return array_keys($this->configuration);
    }

    /** @return list<string> */
    public function variantOptions(DataContainer $dataContainer): array
    {
        if (! $dataContainer->activeRecord || ! $dataContainer->activeRecord->tile_provider) {
            return [];
        }

        $variants = $this->configuration[$dataContainer->activeRecord->tile_provider]['variants'] ?? [];
        $options  = [];

        foreach ($variants as $key => $value) {
            $options[] = is_string($value) ? $value : (string) $key;
        }

        return $options;
    }

    public function termsOfUse(DataContainer $dataContainer): string
    {
        if ($dataContainer->activeRecord === null) {
            return '';
        }

        if (! isset($this->configuration[$dataContainer->activeRecord->tile_provider])) {
            return '';
        }

        $provider = $this->configuration[$dataContainer->activeRecord->tile_provider];
        $url      = $provider['url'] ?? null;

        if (isset($provider['variants'][$dataContainer->activeRecord->tile_provider_variant])) {
            $variant = $provider['variants'][$dataContainer->activeRecord->tile_provider_variant];
            $url     = $variant['url'] ?? $url;
        }

        if ($url !== null) {
            $template = new BackendTemplate('be_cowegis_provider_terms_of_use');
            $template->setData(
                [
                    'text' => $this->translator->trans(
                        'tl_cowegis_layer.tile_provider_terms_of_use',
                        [],
                        'contao_tl_cowegis_layer',
                    ),
                    'url'  => $url,
                ],
            );

            return $template->parse();
        }

        return '';
    }
}
