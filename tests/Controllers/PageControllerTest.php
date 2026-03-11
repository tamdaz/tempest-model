<?php

declare(strict_types=1);

namespace Tests\Controllers;

use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

/**
 * @internal
 */
final class PageControllerTest extends IntegrationTestCase
{
    #[Test]
    public function homepage_is_reachable(): void
    {
        $this->http->get('/')->assertOk();
    }
}
