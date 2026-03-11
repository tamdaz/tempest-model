<?php

declare(strict_types=1);

namespace App\Twig;

use App\Twig\Extensions\DebugExtension;
use App\Twig\Extensions\RoutingExtension;
use Tempest\View\View;
use Tempest\View\ViewRenderer;
use Twig\Environment;
use Twig\Extension\AttributeExtension;

readonly class TwigViewRenderer implements ViewRenderer
{
    public function __construct(
        private Environment $twig,
    ) {}

    public function render(View|string $view): string
    {
        foreach ($this->getAttributeExtensions() as $extension) {
            $this->twig->addExtension(new AttributeExtension($extension));
        }

        return trim($this->twig->render($view->path, $view->data));
    }

    private function getAttributeExtensions(): array
    {
        return [
            DebugExtension::class,
            RoutingExtension::class,
        ];
    }
}
