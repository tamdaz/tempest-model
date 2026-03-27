<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Loader\LoaderInterface;
use Twig\Source;

/**
 * Twig loader that wraps any existing loader and preprocesses template source
 * through {@see ComponentPreprocessor} before handing it to Twig.
 *
 * This enables HTML-like component syntax such as:
 *   <twig:Button label="Click" />
 *   <twig:Card><twig:block name="header">…</twig:block></twig:Card>
 */
final readonly class ComponentLoader implements LoaderInterface
{
    public function __construct(private LoaderInterface $inner) {}

    public function getSourceContext(string $name): Source
    {
        $source = $this->inner->getSourceContext($name);
        $processed = ComponentPreprocessor::process($source->getCode());

        return new Source($processed, $source->getName(), $source->getPath());
    }

    public function getCacheKey(string $name): string
    {
        return $this->inner->getCacheKey($name);
    }

    public function isFresh(string $name, int $time): bool
    {
        return $this->inner->isFresh($name, $time);
    }

    public function exists(string $name): bool
    {
        return $this->inner->exists($name);
    }
}
