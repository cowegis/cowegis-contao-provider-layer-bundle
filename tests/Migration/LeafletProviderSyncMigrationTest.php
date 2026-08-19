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
