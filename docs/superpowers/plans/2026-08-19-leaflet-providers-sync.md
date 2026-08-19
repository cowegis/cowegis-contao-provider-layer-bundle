# Leaflet-Providers Sync Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring `src/Resources/config/config.yaml` back in sync with the upstream `leaflet-extras/leaflet-providers` (`leaflet-providers.js`, master as of 2026-08-19), fix a discovered option-name bug, complete the missing German backend translations, and add a Contao migration that renames existing `tl_cowegis_layer` rows wherever an unambiguous upstream rename exists.

**Architecture:** The bundle's Contao integration (`tl_cowegis_layer` DCA, `LayerDcaListener`, `ProviderPalettesListener`, `ProviderLayerType`, `ProviderLayerHydrator`) is fully data-driven from the `cowegis_contao_provider_layer.providers` parameter (built from `config.yaml`) — none of that PHP code needs to change. The work is: (1) rewrite `config.yaml`, (2) add the two missing German language keys, (3) add one `Contao\CoreBundle\Migration\MigrationInterface` implementation that renames provider/variant values in the database for the subset of changes that have a safe, unambiguous mapping, and reports everything else (no successor, or ambiguous rename) as a manual action in the migration result message.

**Tech Stack:** PHP 8.2, Symfony DI/YAML config, Contao 4.13/5.3 DCA + Migration framework, Doctrine DBAL, PHPUnit.

**Spec:** No separate spec file — the requirements were established in conversation by diffing the upstream `leaflet-providers.js` (fetched from `https://raw.githubusercontent.com/leaflet-extras/leaflet-providers/refs/heads/master/leaflet-providers.js`, plus its `CHANGELOG.md`) against the current `src/Resources/config/config.yaml`, and by reading `src/Resources/contao/dca/tl_cowegis_layer.php`, `src/EventListener/Dca/LayerDcaListener.php`, `src/EventListener/Hook/ProviderPalettesListener.php`, `src/Map/Layer/ProviderLayerType.php`, `src/Map/Layer/ProviderLayerHydrator.php`, and the `de`/`en` language files. The user confirmed: hard sync (remove obsolete providers), migrate via Contao migration wherever an unambiguous mapping exists, and fully migrate HERE to API v3.

## Global Constraints

- `config.yaml`'s `url` key is the **attribution/terms-of-use link**, not a tile URL template — always take it from the `<a href="...">` inside the upstream `options.attribution` string (or the closest equivalent), never invent one.
- `options` in `config.yaml` maps the upstream **tile-option name** (exact case, e.g. `apikey` vs `apiKey`) to the internal DCA field (`tile_provider_key` / `tile_provider_code`) — the option name must match `leaflet-providers.js` byte-for-byte, since it is passed straight through to the Leaflet provider plugin at runtime.
- Every provider that needs a credential must list that DCA field name in both `options` (as the value) and `fields` (as a list entry) — see existing `Thunderforest`/`MapBox` entries as the pattern.
- Do not touch `src/Resources/contao/dca/tl_cowegis_layer.php`, `LayerDcaListener.php`, `ProviderPalettesListener.php`, `ProviderLayerType.php`, or `ProviderLayerHydrator.php` — they already read the configuration generically; no code change is needed there for this sync.
- No new `TL_LANG` keys are needed for provider or variant *names* — the backend select fields have no `reference` array, so Contao displays the raw `config.yaml` keys as labels (confirmed by reading `tl_cowegis_layer.php`).
- **No autowiring.** This bundle wires every service argument explicitly in `services.yaml` (see the existing `$translator`, `$configuration`, `$dcaManager` entries) even though `_defaults.autoconfigure: true` is set — `autoconfigure` only auto-applies tags/lifecycle from implemented interfaces (e.g. auto-tagging a `MigrationInterface` implementation as `contao.migration`), it does not autowire constructor arguments. Any new service definition must list `arguments:` explicitly.
- **Tests use PHPUnit, not phpspec.** Test classes live under `tests/`, namespace `Cowegis\Bundle\ContaoProviderLayer\Test\...`, extend `PHPUnit\Framework\TestCase`, use PHPUnit's native `createMock`/`createStub` — matching the sibling `cowegis-contao-draw-widget-bundle` convention. `phpspec.yml` and the `netzmacht/phpspec-phpcq-plugin` dependency are unused (zero files under `spec/`) and are removed as part of this plan (Task 3).

---

## Task 1: Rewrite `config.yaml`

**Files:**
- Modify: `src/Resources/config/config.yaml`

**Interfaces:**
- Produces: the `cowegis_contao_provider_layer.providers` parameter, keyed by provider name, each value shaped like `TProviderConfig` (`src/Map/Layer/ProviderLayerType.php:19-25`): `array{url: string, variants?: list<string>|array<string,array<string,string>>, options?: array<string,string>, fields?: list<string>}`. Everything downstream (Task 3's migration, and all existing PHP classes) keys off these exact provider/variant string values.

### Reference: exact diff to apply

**Remove entirely** (no upstream successor — confirmed via `CHANGELOG.md`: "[Breaking] Remove deprecated OpenFireMap layer", "[Breaking] Remove Hydda layers", "Remove Wikimedia map provider"):
- `OpenPtMap` (see Task 3 — replaced by `OPNVKarte`, but it's a provider-key rename, not an in-place edit)
- `OpenFireMap`
- `Hydda`
- `Stamen` (merged into `Stadia`, see below)
- `HEREv3` (renamed to `HERE`, see below)
- `Wikimedia`

**`OpenStreetMap`** — add a `CAT` variant after `BZH`, same shape as the existing `HOT`/`BZH` entries:
```yaml
                CAT:
                    url: 'https://www.openstreetmap.cat/'
```

**`Stadia`** — replace the `variants` list with the merged list (upstream moved all `Stamen.*` styles here, "[Breaking] Move Stamen styles to reflect that they are now hosted by Stadia Maps"), and add `AlidadeSatellite`:
```yaml
        Stadia:
            url: 'https://stadiamaps.com/'
            variants:
                - AlidadeSmooth
                - AlidadeSmoothDark
                - AlidadeSatellite
                - OSMBright
                - Outdoors
                - StamenToner
                - StamenTonerBackground
                - StamenTonerLines
                - StamenTonerLabels
                - StamenTonerLite
                - StamenTonerDark
                - StamenTonerBlacklite
                - StamenWatercolor
                - StamenTerrain
                - StamenTerrainBackground
                - StamenTerrainLabels
                - StamenTerrainLines
```

**`Thunderforest`** — add 3 variants, **and fix the option name**: upstream's tile URL uses `apikey` (lowercase, confirmed by reading the raw JS: `` url: '...?apikey={apikey}' ``), but `config.yaml` currently maps `apiKey` (capital K). That mismatch means the key never reaches the tile URL correctly today. Fix it:
```yaml
        Thunderforest:
            url: 'https://www.thunderforest.com/'
            variants:
                - OpenCycleMap
                - Transport
                - TransportDark
                - SpinalMap
                - Landscape
                - Outdoors
                - Pioneer
                - MobileAtlas
                - Neighbourhood
                - Atlas
            options:
                apikey: tile_provider_key
            fields:
                - tile_provider_key
```

**`Jawg`** — add `Lagoon` (insert after `Terrain`, matching upstream order):
```yaml
        Jawg:
            url: 'https://www.jawg.io/lab/'
            variants:
                - Streets
                - Terrain
                - Lagoon
                - Sunny
                - Dark
                - Light
                - Matrix
```

**`MapTiler`** — replace `variants` with the full current upstream list:
```yaml
        MapTiler:
            url: 'https://www.maptiler.com/copyright'
            variants:
                - Streets
                - Basic
                - Bright
                - Pastel
                - Positron
                - Hybrid
                - Toner
                - Topo
                - Voyager
                - Ocean
                - Backdrop
                - Dataviz
                - DatavizLight
                - DatavizDark
                - Aquarelle
                - Landscape
                - Openstreetmap
                - Outdoor
                - Satellite
                - Winter
            options:
                key: tile_provider_key
            fields:
                - tile_provider_key
```

**`Esri`** — remove `DeLorme` (`CHANGELOG.md`: "Removed Esri.DeLorme layer"):
```yaml
        Esri:
            url: 'https://arcgisonline.com'
            variants:
                - WorldStreetMap
                - WorldTopoMap
                - WorldImagery
                - WorldTerrain
                - WorldShadedRelief
                - WorldPhysical
                - OceanBasemap
                - NatGeoWorldMap
                - WorldGrayCanvas
```

**`HERE`** — replace the *entire* existing `HERE` block (old API v2 with `app_id`/`app_code`) with the new v3 scheme (`CHANGELOG.md`: "[Breaking] Remove deprecated HERE API v2 and rename HEREv3 to HERE updated to API v3"). This is the same shape the old `HEREv3` entry already had (single `apiKey` field), just with the corrected upstream variant names and URL:
```yaml
        HERE:
            url: 'https://platform.here.com'
            variants:
                - exploreDay
                - liteDay
                - logisticsDay
                - topoDay
                - logisticsNight
                - exploreNight
                - topoNight
                - liteNight
                - exploreSatelliteDay
                - liteSatelliteDay
                - logisticsSatelliteDay
                - basicMap
                - mapLabels
                - trafficFlow
                - carnavDayGrey
                - hybridDay
                - hybridDayMobile
                - hybridDayTransit
                - hybridDayGrey
                - pedestrianDay
                - pedestrianNight
                - satelliteDay
                - terrainDay
                - terrainDayMobile
            options:
                apiKey: tile_provider_key
            fields:
                - tile_provider_key
```
Delete the old `HEREv3` block below it — it's now redundant with `HERE`.

**`nlmaps`** — add `water` (upstream order: `standaard, pastel, grijs, water, luchtfoto`):
```yaml
        nlmaps:
            url: 'https://www.kadaster.nl/'
            variants:
                - standaard
                - pastel
                - grijs
                - water
                - luchtfoto
```

**`NLS`** — upstream now serves these tiles via MapTiler and requires an API key (previously `config.yaml` had no `variants`/`options` at all for `NLS`):
```yaml
        NLS:
            url: 'http://maps.nls.uk/projects/subscription-api'
            variants:
                - osgb63k1885
                - osgb1888
                - osgb10k1888
                - osgb1919
                - osgb25k1937
                - osgb63k1955
                - oslondon1k1893
            options:
                apikey: tile_provider_key
            fields:
                - tile_provider_key
```

**`GeoportailFrance`** — `ignMaps` was removed from upstream years ago and `maps` no longer exists either; upstream's current variant set is `plan, parcels, orthos`:
```yaml
        GeoportailFrance:
            url: 'https://www.geoportail.gouv.fr/'
            variants:
                - plan
                - parcels
                - orthos
```

**Append these 10 new providers** at the end of the file (after `OneMapSG`), in upstream order:
```yaml
        MapTilesAPI:
            url: 'http://www.maptilesapi.com/'
            variants:
                - OSMEnglish
                - OSMFrancais
                - OSMEspagnol
            options:
                apikey: tile_provider_key
            fields:
                - tile_provider_key

        OPNVKarte:
            url: 'https://memomaps.de/'

        BaseMapDE:
            url: 'http://www.govdata.de/dl-de/by-2-0'
            variants:
                - Color
                - Grey

        USGS:
            url: 'https://www.usgs.gov/'
            variants:
                - USTopo
                - USImagery
                - USImageryTopo

        WaymarkedTrails:
            url: 'https://waymarkedtrails.org'
            variants:
                - hiking
                - cycling
                - mtb
                - slopes
                - riding
                - skating

        OpenAIP:
            url: 'https://www.openaip.net/'

        OpenSnowMap:
            url: 'https://www.opensnowmap.org/iframes/data.html'
            variants:
                - pistes

        AzureMaps:
            url: 'https://docs.microsoft.com/en-us/rest/api/maps/render-v2/get-map-tile'
            variants:
                - MicrosoftImagery
                - MicrosoftBaseDarkGrey
                - MicrosoftBaseRoad
                - MicrosoftBaseHybridRoad
                - MicrosoftTerraMain
                - MicrosoftWeatherInfraredMain
                - MicrosoftWeatherRadarMain
            options:
                subscriptionKey: tile_provider_key
            fields:
                - tile_provider_key

        SwissFederalGeoportal:
            url: 'https://www.swisstopo.admin.ch/'
            variants:
                - NationalMapColor
                - NationalMapGrey
                - SWISSIMAGE

        TopPlusOpen:
            url: 'http://www.govdata.de/dl-de/by-2-0'
            variants:
                - Color
                - Grey
```

Everything else in the file (`OpenSeaMap`, `OpenTopoMap`, `OpenRailwayMap`, `SafeCast`, `CyclOSM`, `MapBox`, `TomTom`, `OpenWeatherMap`, `FreeMapSK`, `MtbMap`, `CartoDB`, `HikeBike`, `BasemapAT`, `NASAGIBS`, `JusticeMap`, `OneMapSG`) is unchanged — leave those blocks exactly as they are.

- [ ] **Step 1: Apply the diff above to `src/Resources/config/config.yaml`**

Use the reference blocks above verbatim (they preserve the file's existing 4-space indentation style).

- [ ] **Step 2: Verify the YAML parses and has the expected top-level provider keys**

Run:
```bash
php -r '
require __DIR__ . "/vendor/autoload.php";
use Symfony\Component\Yaml\Yaml;
$data = Yaml::parseFile(__DIR__ . "/src/Resources/config/config.yaml");
$providers = $data["parameters"]["cowegis_contao_provider_layer.providers"];
sort($keys = array_keys($providers));
echo implode(PHP_EOL, $keys), PHP_EOL;
echo "Total: " . count($keys) . PHP_EOL;
'
```
Expected: valid YAML (no exception), 36 provider keys, containing `AzureMaps`, `BaseMapDE`, `HERE` (and **not** `HEREv3`), `MapTilesAPI`, `OPNVKarte` (and **not** `OpenPtMap`), `OpenAIP`, `OpenSnowMap`, `SwissFederalGeoportal`, `TopPlusOpen`, `USGS`, `WaymarkedTrails`, `WaymarkedTrails`, `Stadia` (and **not** `Stamen`), and **not** `OpenFireMap`, `Hydda`, `Wikimedia`.

- [ ] **Step 3: Commit**

```bash
git add src/Resources/config/config.yaml
git commit -m "Sync tile provider configuration with upstream leaflet-providers.js"
```

---

## Task 2: Complete the German backend language file

**Context:** `src/Resources/contao/languages/en/tl_cowegis_layer.php` defines 5 keys (`tile_provider`, `tile_provider_variant`, `tile_provider_key`, `tile_provider_code`, `tile_provider_terms_of_use`). `src/Resources/contao/languages/de/tl_cowegis_layer.php` only defines the first 2 — `tile_provider_key`, `tile_provider_code`, and `tile_provider_terms_of_use` are missing, so German backend users currently see empty labels for the API-key/access-token field, the app-code field, and the terms-of-use notice. This is independent of the provider sync and should be fixed regardless.

**Files:**
- Modify: `src/Resources/contao/languages/de/tl_cowegis_layer.php`

- [ ] **Step 1: Add the 3 missing keys**

```php
$GLOBALS['TL_LANG']['tl_cowegis_layer']['tile_provider_key']['0']    = 'API-Key / Access-Token';
$GLOBALS['TL_LANG']['tl_cowegis_layer']['tile_provider_key']['1']    = 'Bitte geben Sie den API-Key / Access-Token an, der für diesen Kachelanbieter benötigt wird.';
$GLOBALS['TL_LANG']['tl_cowegis_layer']['tile_provider_code']['0']   = 'App-Code';
$GLOBALS['TL_LANG']['tl_cowegis_layer']['tile_provider_code']['1']   = 'Bitte geben Sie den App-Code an, der für diesen Kachelanbieter benötigt wird.';
$GLOBALS['TL_LANG']['tl_cowegis_layer']['tile_provider_terms_of_use'] = 'Bitte beachten Sie die Nutzungsbedingungen und Datenschutzbestimmungen des Anbieters. Weitere Informationen finden Sie auf der Website des Anbieters: ';
```

Append them at the end of the existing file, keeping the same `$GLOBALS['TL_LANG']['tl_cowegis_layer'][...]` style already used in that file.

- [ ] **Step 2: Verify the file is valid PHP**

Run: `php -l src/Resources/contao/languages/de/tl_cowegis_layer.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add src/Resources/contao/languages/de/tl_cowegis_layer.php
git commit -m "Add missing German translations for tile provider key/code/terms fields"
```

---

## Task 3: Remove phpspec, set up PHPUnit test infrastructure

**Context:** `phpspec.yml` and `netzmacht/phpspec-phpcq-plugin` (composer.json `require-dev`) are configured but unused — there are zero files under `spec/`. Task 4 needs a real test for the new migration class, and per project convention that must be PHPUnit, matching `cowegis-contao-draw-widget-bundle`'s layout (`tests/`, namespace `Cowegis\Bundle\<X>\Test\...`, `phpunit.xml.dist` with schema 9.6). That sibling bundle's `companion.json` also shows the pattern this project's `companion.json` needs: a `config.directories: ["tests"]` entry, and no override disabling the phpcq `phpunit` plugin — this repo's `companion.json` currently has `"phpcq": {"plugins": {"phpunit": false}}`, presumably set at a time when there were no tests yet.

**Files:**
- Delete: `phpspec.yml`
- Modify: `composer.json` (drop `netzmacht/phpspec-phpcq-plugin` from `require-dev`; add an `autoload-dev` PSR-4 mapping for `tests/`)
- Modify: `companion.json` (remove the `"phpunit": false` plugin override; add `"directories": ["tests"]` under `config`)
- Create: `phpunit.xml.dist`
- Create: `tests/` (directory; populated by Task 4)

- [ ] **Step 1: Delete the unused phpspec config**

```bash
git rm phpspec.yml
```

- [ ] **Step 2: Remove the phpspec phpcq plugin dependency from `composer.json`**

In the `require-dev` block, remove:
```json
"netzmacht/phpspec-phpcq-plugin": "@dev",
```

- [ ] **Step 3: Add an `autoload-dev` PSR-4 mapping for `tests/`**

`composer.json` currently has no `autoload-dev` block. Add one at the top level (sibling to `autoload`):
```json
"autoload-dev": {
    "psr-4": {
        "Cowegis\\Bundle\\ContaoProviderLayer\\Test\\": "tests/"
    }
}
```

- [ ] **Step 4: Update `companion.json`**

Remove the `"phpunit": false` line from `tools.phpcq.plugins` (drop the now-empty `"plugins": {}` block entirely if nothing else is in it), and add a `directories` entry under `tools.config`:
```json
{
  "receipts": ["projects/contao-bundle/4.13-5.3"],
  "config": {
    "phpConstraint": "^8.2",
    "directories": ["tests"]
  },
  "tools": {
    "composer": {
      "namespace": "Cowegis\\Bundle\\ContaoProviderLayer"
    },
    "psalm": {
      "configuration": {
        "errorLevel": "3"
      }
    },
    "phpcq": {
      "presets": {
        "composer-require-checker": {
          "tasks": {
            "composer-require-checker": {
              "config": {
                "config_file": ".composer-require-checker.json"
              }
            }
          }
        },
        "phpcpd": {
          "tasks": {
            "phpcpd": {
              "config": {
                "exclude": [
                  "src/Resources/contao/dca/tl_cowegis_popup.php",
                  "src/Resources/contao/dca/tl_cowegis_style.php"
                ]
              }
            }
          }
        }
      }
    }
  }
}
```

- [ ] **Step 5: Add `phpunit.xml.dist`**

Copy the proven template from `cowegis-contao-draw-widget-bundle/phpunit.xml.dist`:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.6/phpunit.xsd"
        colors="true"
        bootstrap="vendor/autoload.php">
    <coverage>
        <include>
            <directory>./src/</directory>
        </include>
    </coverage>
    <testsuites>
        <testsuite name="tests">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 6: Create the `tests/` directory**

```bash
mkdir -p tests
```
(It will be populated by Task 4's test — an empty directory isn't tracked by git, so this is a no-op for the commit until Task 4 adds a file into it.)

- [ ] **Step 7: Regenerate composer's autoloader and verify**

Run: `composer dump-autoload`
Expected: no errors; `Cowegis\Bundle\ContaoProviderLayer\Test\` is listed in the generated `vendor/composer/autoload_psr4.php`.

- [ ] **Step 8: Regenerate phpcq tooling config (manual step, requires a terminal)**

`companion` runs in an interactive Docker container and can't be driven by an agent. Ask the user to run it themselves, e.g. via `! companion <sync-command>` (whatever their usual regeneration command is), to pick up the `companion.json` changes into `.phpcq.yaml.dist` / `.phpcq.lock` — this wires the phpcq `phpunit` plugin into the `analyze` task the same way `cowegis-contao-draw-widget-bundle/.phpcq.yaml.dist` already does. The hand-authored `phpunit.xml.dist` from Step 5 already makes `vendor/bin/phpunit` / `phpcq run phpunit` work regardless, so this step only affects the phpcq-managed tool installation and task wiring, not test execution.

- [ ] **Step 9: Commit**

```bash
git add composer.json companion.json phpunit.xml.dist
git commit -m "Replace unused phpspec setup with PHPUnit test infrastructure"
```

---

## Task 4: Contao migration for renamed/merged providers

**Context:** `tl_cowegis_layer.tile_provider` / `tile_provider_variant` store the raw `config.yaml` keys as free-text strings (varchar(32), no FK). Task 1 removes or renames some of those keys, so existing rows referencing them need a database migration. Reference pattern: `Cowegis\Bundle\Contao\Migration\LeafletMigration` in the sibling `cowegis-contao-bundle` repo (`extends AbstractMigration`, `getName()`/`shouldRun()`/`run(): MigrationResult`, `$this->createResult(bool $successful, string $message)`).

What can be migrated automatically (unambiguous 1:1 rename, confirmed via upstream `CHANGELOG.md`):
| Old | New | Confidence |
|---|---|---|
| provider `OpenPtMap` | provider `OPNVKarte` | High — CHANGELOG: "Replace OpenPtMap overlay by OPNVKarte layer" |
| provider `HEREv3` | provider `HERE` | High — CHANGELOG: "...rename HEREv3 to HERE updated to API v3"; both already use a single `apiKey` credential in this bundle's config, so no credential breakage |
| provider `Stamen` + variant `Toner`/`TonerBackground`/`TonerLines`/`TonerLabels`/`TonerLite`/`Watercolor`/`Terrain`/`TerrainBackground`/`TerrainLabels` | provider `Stadia` + variant `Stamen<Name>` | High — CHANGELOG: "Move Stamen styles to reflect that they are now hosted by Stadia Maps"; identical style, renamed with a `Stamen` prefix |

What **cannot** be migrated automatically (must be reported, not guessed):
- `Stamen` rows with variant `TonerHybrid`, `TopOSMRelief`, or `TopOSMFeatures` — these 3 styles have no successor anywhere upstream. The migration still moves the provider to `Stadia` (so the layer stays resolvable and doesn't reference a fully-deleted provider), but leaves the variant value untouched; the existing `LayerDcaListener::initialize()` fallback (`src/EventListener/Dca/LayerDcaListener.php:65-79`) will silently pick the first available `Stadia` variant the next time the record is opened in the backend, so the site keeps rendering (with a different style) rather than breaking.
- Rows with provider `HERE` that still have a non-empty `tile_provider_code` — these used the old v2 `app_id`/`app_code` credentials, which HERE's v3 API does not accept. There is no way to derive a v3 `apiKey` from old v2 credentials, so this **cannot** be auto-fixed. The migration only reports the affected row IDs; a human needs to request a new key at `https://platform.here.com/portal/` and re-enter it.
- Rows with provider `OpenFireMap`, `Hydda`, or `Wikimedia` — removed upstream with no replacement at all (confirmed via CHANGELOG "[Breaking] Remove ..." entries). Reported only, left untouched (an admin must pick a different provider).
- Rows with provider `Esri` and variant `DeLorme` — removed upstream with no replacement. Reported only.
- Rows with provider `GeoportailFrance` and variant `ignMaps` or `maps` — both already gone from upstream for years (`ignMaps` removed in 2018 per CHANGELOG) with no single confirmed successor. Reported only.

**Files:**
- Create: `src/Migration/LeafletProviderSyncMigration.php`
- Modify: `src/Resources/config/services.yaml` (register the migration service explicitly — this bundle does not use autowiring, see Global Constraints)
- Test: `tests/Migration/LeafletProviderSyncMigrationTest.php`

**Interfaces:**
- Consumes: `Doctrine\DBAL\Connection`, passed in explicitly via `services.yaml` as `@doctrine.dbal.default_connection` (no autowiring — see Global Constraints).
- Produces: nothing consumed by other tasks — this is a leaf migration, executed once via `vendor/bin/contao-console contao:migrate`.

- [ ] **Step 1: Write the failing PHPUnit test**

```php
<?php

declare(strict_types=1);

namespace Cowegis\Bundle\ContaoProviderLayer\Test\Migration;

use Cowegis\Bundle\ContaoProviderLayer\Migration\LeafletProviderSyncMigration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use PHPUnit\Framework\TestCase;

final class LeafletProviderSyncMigrationTest extends TestCase
{
    public function testItDoesNotRunWhenTableIsMissing(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->with(['tl_cowegis_layer'])->willReturn(false);

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        $migration = new LeafletProviderSyncMigration($connection);

        self::assertFalse($migration->shouldRun());
    }

    public function testItDoesNotRunWhenNoAffectedRowsExist(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);
        $connection->method('fetchOne')->willReturn('0');

        $migration = new LeafletProviderSyncMigration($connection);

        self::assertFalse($migration->shouldRun());
    }

    public function testItRunsWhenAffectedRowsExist(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);
        $connection->method('fetchOne')->willReturn('2');

        $migration = new LeafletProviderSyncMigration($connection);

        self::assertTrue($migration->shouldRun());
    }

    public function testItRenamesOpenPtMapToOpnvkarteAndReportsIt(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([]);
        $connection
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params = []): int {
                if (($params['old'] ?? null) === 'OpenPtMap') {
                    self::assertSame(['new' => 'OPNVKarte', 'old' => 'OpenPtMap'], $params);

                    return 3;
                }

                return 0;
            });

        $migration = new LeafletProviderSyncMigration($connection);
        $result    = $migration->run();

        self::assertTrue($result->isSuccessful());
        self::assertStringContainsString(
            'Renamed 3 layer(s) from provider "OpenPtMap" to "OPNVKarte"',
            (string) $result->getMessage(),
        );
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Migration/LeafletProviderSyncMigrationTest.php`
Expected: FAIL — class `Cowegis\Bundle\ContaoProviderLayer\Migration\LeafletProviderSyncMigration` not found.

- [ ] **Step 3: Implement the migration**

```php
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
        'HEREv3'    => 'HERE',
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

        if ($messages === []) {
            return $this->createResult(true, 'Nothing to migrate.');
        }

        return $this->createResult(true, implode("\n", $messages));
    }

    private function affectedRowCount(): int
    {
        $providers = [
            ...array_keys(self::PROVIDER_RENAMES),
            'Stamen',
            ...self::PROVIDERS_WITHOUT_REPLACEMENT,
        ];

        $placeholders = implode(',', array_fill(0, count($providers), '?'));

        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM tl_cowegis_layer WHERE tile_provider IN ($placeholders) "
                . "OR (tile_provider = 'HERE' AND tile_provider_code != '') "
                . "OR (tile_provider = 'GeoportailFrance' AND tile_provider_variant IN ('ignMaps', 'maps')) "
                . "OR (tile_provider = 'Esri' AND tile_provider_variant = 'DeLorme')",
            $providers,
        );
    }
}
```

**Implementation note for whoever picks this up:** `createSchemaManager()` is the Doctrine DBAL 3.x/4.x method name; DBAL 2.x (still possible under the `contao/core-bundle: ^4.13` lower bound) uses `getSchemaManager()`. Check `composer show doctrine/dbal` in the target environment before merging and adjust if needed — this plan targets DBAL 3+.

- [ ] **Step 4: Register the migration in `services.yaml` with explicit arguments**

Add to `src/Resources/config/services.yaml`, following the exact explicit-argument style already used by every other service in that file (no autowiring — see Global Constraints):
```yaml
    Cowegis\Bundle\ContaoProviderLayer\Migration\LeafletProviderSyncMigration:
        arguments:
            $connection: '@doctrine.dbal.default_connection'
```
`autoconfigure: true` (already set in `_defaults`) still auto-tags this as `contao.migration` because it implements `MigrationInterface` — that part needs no extra config. Only the constructor argument must be listed explicitly.

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/phpunit tests/Migration/LeafletProviderSyncMigrationTest.php`
Expected: PASS (all 4 tests green)

- [ ] **Step 6: Commit**

```bash
git add src/Migration/LeafletProviderSyncMigration.php src/Resources/config/services.yaml tests/Migration/LeafletProviderSyncMigrationTest.php
git commit -m "Add Contao migration for renamed/merged tile providers"
```

---

## Self-Review Notes

- **Spec coverage:** config.yaml sync (Task 1) ✓, Contao DCA adjustments — determined none are needed, documented why (Global Constraints + Task 4 context) ✓, languages (Task 2) ✓, phpspec removal / PHPUnit setup (Task 3) ✓, migration for renames where possible with a PHPUnit test (Task 4) ✓, no-autowiring convention applied to the new service (Task 4, Step 4) ✓.
- **Known residual risk, not auto-fixable by any migration:** sites with layers on the old `HERE` (v2) provider need a **new HERE Platform API key** obtained manually — flagged in the migration's `MigrationResult` message, not solvable in code.
- **Known manual step, not automatable by an agent:** regenerating `.phpcq.yaml.dist`/`.phpcq.lock` via the `companion` CLI (Task 3, Step 8) needs an interactive terminal — ask the user to run it themselves.
- **Before running in production:** run `vendor/bin/contao-console contao:migrate --dry-run` first and read the reported messages — they list every affected row ID needing manual follow-up.
