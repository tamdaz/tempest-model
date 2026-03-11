<?php

declare(strict_types=1);

namespace App\Makers\Stubs;

use Tempest\Discovery\SkipDiscovery;
use Tempest\Router\Get;

#[SkipDiscovery]
class ControllerStub
{
    public function __construct()
    {
        // Inject any dependencies here
    }

    #[Get(uri: '/')]
    public function index(): void
    {
        // Add your logic here.
    }
}
