<?php

declare(strict_types=1);

use function Tempest\env;
use function Tempest\root_path;
use Tempest\Database\Config\SQLiteConfig;

/**
 * Database configuration
 * @see <URL>
 */
return new SQLiteConfig(
    path: root_path(env('DATABASE_PATH', 'tmp/database.sqlite3')),
);
