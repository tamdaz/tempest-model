<?php

declare(strict_types=1);

namespace App\Twig;

use App\Twig\Extensions\DebugExtension;
use App\Twig\Extensions\RoutingExtension;
use App\Twig\Extensions\ViteExtension;
use Tempest\View\View;
use Tempest\View\ViewRenderer;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Extension\AttributeExtension;

readonly class TwigViewRenderer implements ViewRenderer
{
    /**
     * Wraps the Twig environment and ensures the component loader is enabled.
     *
     * @param Environment $twig Twig environment used for rendering.
     */
    public function __construct(
        private Environment $twig
    )
    {
        if (! $twig->getLoader() instanceof ComponentLoader) {
            $twig->setLoader(new ComponentLoader($twig->getLoader()));
        }
    }

    /**
     * Renders a Twig view or template name to HTML.
     *
     * @param View|string $view View instance or template name to render.
     * @return string Rendered HTML.
     * @throws LoaderError When the template cannot be loaded.
     * @throws RuntimeError When a runtime error occurs during rendering.
     * @throws SyntaxError When the template contains invalid Twig syntax.
     */
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
     * @return class-string[] Fully qualified extension class names.
     */
    private function getAttributeExtensions(): array
    {
        return [
            DebugExtension::class,
            RoutingExtension::class,
            ViteExtension::class
        ];
    }
}
