<?php

declare(strict_types=1);

use App\Twig\TwigViewRenderer;
use Tempest\View\ViewConfig;

return new ViewConfig(rendererClass: TwigViewRenderer::class);
