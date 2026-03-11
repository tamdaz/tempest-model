<?php

declare(strict_types=1);

namespace App\Controllers;

use Tempest\Router\Get;
use Tempest\View\View;

use function Tempest\View\view;

final readonly class PageController
{
    #[Get(uri: '/')]
    public function index(): View
    {
        return view('index.html.twig');
    }
}
