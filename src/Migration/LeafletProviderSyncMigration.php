<?php

declare(strict_types=1);

namespace Cowegis\Bundle\ContaoProviderLayer\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Override;

use function array_fill;
use function array_keys;
use function count;
use function implode;
use function sprintf;

final class LeafletProviderSyncMigration extends AbstractMigration
{
    /** @var array<string,string> Provider keys renamed 1:1 upstream, no variant impact. */
    private const PROVIDER_RENAMES = [
        'OpenPtMap' => 'OPNVKarte',
    ];

    /** @var array<string,string> Stamen variant name => new Stadia variant name (confirmed 1:1 style match). */
    private const STAMEN_VARIANT_RENAMES = [
        'Toner'             => 'StamenToner',
        'TonerBackground'   => 'StamenTonerBackground',
        'TonerLines'        => 'StamenTonerLines',
        'TonerLabels'       => 'StamenTonerLabels',
        'TonerLite'         => 'StamenTonerLite',
        'Watercolor'        => 'StamenWatercolor',
        'Terrain'           => 'StamenTerrain',
        'TerrainBackground' => 'StamenTerrainBackground',
        'TerrainLabels'     => 'StamenTerrainLabels',
    ];

    /** @var list<string> Providers removed upstream without any replacement. */
    private const PROVIDERS_WITHOUT_REPLACEMENT = ['OpenFireMap', 'Hydda', 'Wikimedia'];

    /** @var list<string> HERE variant names that still exist in the current (v3) config.yaml. */
    private const HERE_VALID_VARIANTS = [
        'exploreDay',
        'liteDay',
        'logisticsDay',
        'topoDay',
        'logisticsNight',
        'exploreNight',
        'topoNight',
        'liteNight',
        'exploreSatelliteDay',
        'liteSatelliteDay',
        'logisticsSatelliteDay',
        'basicMap',
        'mapLabels',
        'trafficFlow',
        'carnavDayGrey',
        'hybridDay',
        'hybridDayMobile',
        'hybridDayTransit',
        'hybridDayGrey',
        'pedestrianDay',
        'pedestrianNight',
        'satelliteDay',
        'terrainDay',
        'terrainDayMobile',
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    #[Override]
    public function getName(): string
    {
        return 'Cowegis provider layer: sync tile providers with leaflet-providers.js (2026-08)';
    }

    #[Override]
    public function shouldRun(): bool
    {
        if (! $this->connection->createSchemaManager()->tablesExist(['tl_cowegis_layer'])) {
            return false;
        }

        return $this->affectedRowCount() > 0;
    }

    #[Override]
    public function run(): MigrationResult
    {
        $messages = [];

        foreach (self::PROVIDER_RENAMES as $old => $new) {
            $count = $this->connection->executeStatement(
                'UPDATE tl_cowegis_layer SET tile_provider = :new WHERE tile_provider = :old',
                ['new' => $new, 'old' => $old],
            );

            if ($count > 0) {
                $messages[] = sprintf('Renamed %d layer(s) from provider "%s" to "%s".', $count, $old, $new);
            }
        }

        $hereV3Count = $this->connection->executeStatement(
            "UPDATE tl_cowegis_layer SET tile_provider = 'HERE', tile_provider_code = '' WHERE tile_provider = 'HEREv3'",
        );

        if ($hereV3Count > 0) {
            $messages[] = sprintf('Renamed %d layer(s) from provider "HEREv3" to "HERE".', $hereV3Count);
        }

        foreach (self::STAMEN_VARIANT_RENAMES as $old => $new) {
            $count = $this->connection->executeStatement(
                'UPDATE tl_cowegis_layer SET tile_provider = :stadia, tile_provider_variant = :new '
                    . 'WHERE tile_provider = :stamen AND tile_provider_variant = :old',
                ['stadia' => 'Stadia', 'new' => $new, 'stamen' => 'Stamen', 'old' => $old],
            );

            if ($count > 0) {
                $messages[] = sprintf('Migrated %d Stamen.%s layer(s) to Stadia.%s.', $count, $old, $new);
            }
        }

        $orphanedStamenIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM tl_cowegis_layer WHERE tile_provider = :stamen',
            ['stamen' => 'Stamen'],
        );

        if ($orphanedStamenIds !== []) {
            $this->connection->executeStatement(
                'UPDATE tl_cowegis_layer SET tile_provider = :stadia WHERE tile_provider = :stamen',
                ['stadia' => 'Stadia', 'stamen' => 'Stamen'],
            );

            $messages[] = sprintf(
                'Moved layer(s) %s to Stadia without a matching style (Stamen.TonerHybrid/TopOSMRelief/'
                    . 'TopOSMFeatures no longer exist upstream) - please pick a new variant manually.',
                implode(', ', $orphanedStamenIds),
            );
        }

        $hereV2Ids = $this->connection->fetchFirstColumn(
            "SELECT id FROM tl_cowegis_layer WHERE tile_provider = 'HERE' AND tile_provider_code != ''",
        );

        if ($hereV2Ids !== []) {
            $messages[] = sprintf(
                'Layer(s) %s use the discontinued HERE API v2 (app_id/app_code), which HERE no longer '
                    . 'accepts. Please request a new Platform apiKey at https://platform.here.com/portal/ '
                    . 'and re-enter it in the layer settings.',
                implode(', ', $hereV2Ids),
            );
        }

        $herePlaceholders        = implode(',', array_fill(0, count(self::HERE_VALID_VARIANTS), '?'));
        $hereInvalidVariantIds   = $this->connection->fetchFirstColumn(
            "SELECT id FROM tl_cowegis_layer WHERE tile_provider = 'HERE' "
                . "AND tile_provider_variant NOT IN ($herePlaceholders)",
            self::HERE_VALID_VARIANTS,
        );

        if ($hereInvalidVariantIds !== []) {
            $messages[] = sprintf(
                "Layer(s) %s use a HERE variant that no longer exists (HERE's API v3 renamed its whole "
                    . 'style catalogue). Please pick a new variant manually.',
                implode(', ', $hereInvalidVariantIds),
            );
        }

        foreach (self::PROVIDERS_WITHOUT_REPLACEMENT as $provider) {
            $ids = $this->connection->fetchFirstColumn(
                'SELECT id FROM tl_cowegis_layer WHERE tile_provider = :provider',
                ['provider' => $provider],
            );

            if ($ids !== []) {
                $messages[] = sprintf(
                    'Layer(s) %s use the removed provider "%s", which has no upstream replacement. '
                        . 'Please choose a different provider manually.',
                    implode(', ', $ids),
                    $provider,
                );
            }
        }

        $orphanedGeoportailIds = $this->connection->fetchFirstColumn(
            "SELECT id FROM tl_cowegis_layer WHERE tile_provider = 'GeoportailFrance' "
                . "AND tile_provider_variant IN ('ignMaps', 'maps')",
        );

        if ($orphanedGeoportailIds !== []) {
            $messages[] = sprintf(
                'Layer(s) %s use GeoportailFrance.ignMaps/maps, which no longer exist upstream. '
                    . 'Please pick a new variant (plan/parcels/orthos) manually.',
                implode(', ', $orphanedGeoportailIds),
            );
        }

        $delormeIds = $this->connection->fetchFirstColumn(
            "SELECT id FROM tl_cowegis_layer WHERE tile_provider = 'Esri' AND tile_provider_variant = 'DeLorme'",
        );

        if ($delormeIds !== []) {
            $messages[] = sprintf(
                'Layer(s) %s use Esri.DeLorme, which no longer exists upstream. Please pick a new '
                    . 'variant manually.',
                implode(', ', $delormeIds),
            );
        }

        $nlsMissingKeyIds = $this->connection->fetchFirstColumn(
            "SELECT id FROM tl_cowegis_layer WHERE tile_provider = 'NLS' AND tile_provider_key = ''",
        );

        if ($nlsMissingKeyIds !== []) {
            $messages[] = sprintf(
                'Layer(s) %s use NLS, which now requires an API key (previously none was needed). '
                    . 'Please request one at https://www.maptiler.com/ and enter it in the layer settings, '
                    . 'and pick one of the new NLS variants.',
                implode(', ', $nlsMissingKeyIds),
            );
        }

        if ($messages === []) {
            return $this->createResult(true, 'Nothing to migrate.');
        }

        return $this->createResult(true, implode("\n", $messages));
    }

    private function affectedRowCount(): int
    {
        $providers = [...array_keys(self::PROVIDER_RENAMES), 'HEREv3', 'Stamen'];

        $placeholders = implode(',', array_fill(0, count($providers), '?'));

        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM tl_cowegis_layer WHERE tile_provider IN ($placeholders)",
            $providers,
        );
    }
}
