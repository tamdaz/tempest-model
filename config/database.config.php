<?php

declare(strict_types=1);

use function Tempest\env;
use function Tempest\root_path;
use Tempest\Database\Config\SQLiteConfig;

return new SQLiteConfig(
    path: root_path(__DIR__ . '/' . env('DATABASE_PATH')),
);
