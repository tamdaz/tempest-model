<?php

declare(strict_types=1);

use Tempest\View\Renderers\TwigConfig;

return new TwigConfig(
    viewPaths: [
        __DIR__ . '/../templates'
    ],
    cachePath: __DIR__ . '/../.tempest/cache/views',
    debug: true
);
