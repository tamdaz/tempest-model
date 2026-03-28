<?php

declare(strict_types=1);

use Tempest\Vite\ViteConfig;

return new ViteConfig(
    entrypoints: [
        'assets/css/app.css',
        'assets/js/app.js'
    ]
);
