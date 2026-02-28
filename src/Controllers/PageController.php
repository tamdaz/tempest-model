<?php

namespace App\Controllers;

use Tempest\Router\Get;
use Tempest\View\View;

use function Tempest\View\view;

final class PageController
{
    #[Get(uri: '/')]
    public function index(): View
    {
        return view(__DIR__ . '/../../templates/index.view.php');
    }
}
