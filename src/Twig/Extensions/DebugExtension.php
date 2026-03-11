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
    public static function dump(mixed ...$vars): string
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
     * Check if a variable is an instance of a class.
     */
    #[AsTwigFunction("instanceof")]
    public static function isInstanceOf(mixed $var, string $class): bool
    {
        return $var instanceof $class;
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
    public static function toJson(mixed $value, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($value, $flags);
    }

    /**
     * Get an array of keys from an array.
     */
    #[AsTwigFunction("array_keys")]
    public static function arrayKeys(array $array): array
    {
        return array_keys($array);
    }

    /**
     * Get an array of values from an array.
     */
    #[AsTwigFunction("array_values")]
    public static function arrayValues(array $array): array
    {
        return array_values($array);
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
    public static function currentUrl(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    /**
     * Check if current URL matches a pattern.
     */
    #[AsTwigFunction("url_matches")]
    public static function urlMatches(string $pattern): bool
    {
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '/';
        return fnmatch($pattern, $currentUrl);
    }

    /**
     * Get current timestamp.
     */
    #[AsTwigFunction("now")]
    public static function now(): int
    {
        return time();
    }

    /**
     * Format a date.
     */
    #[AsTwigFunction("date_format")]
    public static function dateFormat(mixed $date, string $format = 'Y-m-d H:i:s'): string
    {
        if (is_numeric($date)) {
            return date($format, $date);
        }
        
        if ($date instanceof \DateTimeInterface) {
            return $date->format($format);
        }
        
        return date($format, strtotime($date));
    }
}
