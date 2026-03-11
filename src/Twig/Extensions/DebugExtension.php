<?php

declare(strict_types=1);

namespace App\Twig\Extensions;

use Twig\Attribute\AsTwigFunction;

class DebugExtension
{
    /**
     * Dump - dumps variables without stopping execution.
     */
    #[AsTwigFunction("dump")]
    public static function dump(mixed ...$vars): string|false
    {
        ob_start();
        dump($vars);
        return ob_get_clean();
    }

    /**
     * Get the class name of an object.
     */
    #[AsTwigFunction("class")]
    public static function getClass(object $object): string
    {
        return get_class($object);
    }

    /**
     * Check if a variable is empty.
     */
    #[AsTwigFunction("is_empty")]
    public static function isEmpty(mixed $var): bool
    {
        return empty($var);
    }

    /**
     * Get the type of variable.
     */
    #[AsTwigFunction("get_type")]
    public static function getType(mixed $var): string
    {
        return gettype($var);
    }

    /**
     * Get environment variable.
     */
    #[AsTwigFunction("env")]
    public static function env(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }

    /**
     * Convert a value to JSON.
     */
    #[AsTwigFunction("to_json")]
    public static function toJson(mixed $value, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE): string|false
    {
        return json_encode($value, $flags);
    }

    /**
     * Count elements in an array or countable object.
     */
    #[AsTwigFunction("count")]
    public static function count(mixed $var): int
    {
        return is_countable($var) ? count($var) : 0;
    }

    /**
     * Get the current URL (simplified version).
     */
    #[AsTwigFunction("current_url")]
    public static function currentUrl(): mixed
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    /**
     * Get current timestamp.
     */
    #[AsTwigFunction("now")]
    public static function now(): int
    {
        return time();
    }
}
