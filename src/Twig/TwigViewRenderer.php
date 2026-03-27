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
        private Environment $twig
    ) {
        if (!($twig->getLoader() instanceof ComponentLoader)) {
            $twig->setLoader(new ComponentLoader($twig->getLoader()));
        }
    }

    public function render(View|string $view): string
    {
        foreach ($this->getAttributeExtensions() as $extension) {
            $this->twig->addExtension(new AttributeExtension($extension));
        }

        if (is_string($view)) {
            return trim($this->twig->render($view, []));
        }

        return trim($this->twig->render($view->path, $view->data));
    }

    /**
     * Returns an array of Twig class extensions.
     *
     * @return class-string[]
     */
    private function getAttributeExtensions(): array
    {
        return [
            DebugExtension::class,
            RoutingExtension::class
        ];
    }
}
