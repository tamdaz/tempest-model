<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Error\LoaderError;
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
    /**
     * Wraps the inner Twig loader and applies component preprocessing.
     *
     * @param LoaderInterface $inner Loader used to resolve template source.
     */
    public function __construct(
        private LoaderInterface $inner
    ) {}

    /**
     * Returns the preprocessed source context for a template.
     *
     * @param string $name Template name to load.
     * @return Source Resolved template source after component preprocessing.
     * @throws LoaderError When the inner loader cannot find or load the template.
     */
    public function getSourceContext(string $name): Source
    {
        $source = $this->inner->getSourceContext($name);
        $processed = ComponentPreprocessor::process($source->getCode());

        return new Source($processed, $source->getName(), $source->getPath());
    }

    /**
     * Returns the cache key for a template.
     *
     * @param string $name Template name to load.
     * @return string Cache key for the template.
     * @throws LoaderError When the inner loader cannot resolve the template.
     */
    public function getCacheKey(string $name): string
    {
        return $this->inner->getCacheKey($name);
    }

    /**
     * Checks if the template is still fresh.
     *
     * @param string $name Template name to check.
     * @param int $time Unix timestamp used as the freshness reference.
     * @return bool True when the template has not changed since the given time.
     * @throws LoaderError When the inner loader cannot resolve the template.
     */
    public function isFresh(string $name, int $time): bool
    {
        return $this->inner->isFresh($name, $time);
    }

    /**
     * Checks if a template exists in the inner loader.
     *
     * @param string $name Template name to check.
     * @return bool True when the template exists in the inner loader.
     */
    public function exists(string $name): bool
    {
        return $this->inner->exists($name);
    }
}
