<?php

declare(strict_types=1);

use Tempest\Database\Config\SQLiteConfig;

use function Tempest\env;
use function Tempest\root_path;

/**
 * Database configuration
 * @see <URL>
 */
return new SQLiteConfig(
    path: root_path((string) env('DATABASE_PATH', 'tmp/database.sqlite3'))
);
