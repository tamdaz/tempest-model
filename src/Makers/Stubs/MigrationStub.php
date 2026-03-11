<?php

declare(strict_types=1);

namespace App\Makers\Stubs;

use Tempest\Database\MigratesDown;
use Tempest\Database\MigratesUp;
use Tempest\Database\QueryStatement;
use Tempest\Database\QueryStatements\CreateTableStatement;
use Tempest\Database\QueryStatements\DropTableStatement;
use Tempest\Discovery\SkipDiscovery;

#[SkipDiscovery]
final class MigrationStub implements MigratesUp, MigratesDown
{
    /**
     * @var string Migration name.
     */
    public string $name = '0000-00-01_migration_stub';

    /**
     * @var string Table name.
     */
    public string $tableName = 'stub_table';

    /**
     * Runs the migration.
     *
     * @return QueryStatement
     */
    public function up(): QueryStatement
    {
        return new CreateTableStatement($this->tableName)
            ->primary()
            ->datetime('created_at')
            ->datetime('updated_at', nullable: true);
    }

    /**
     * Rollbacks the migration.
     *
     * @return QueryStatement
     */
    public function down(): QueryStatement
    {
        return new DropTableStatement($this->tableName);
    }
}
