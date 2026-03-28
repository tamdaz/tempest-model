<?php

declare(strict_types=1);

namespace App\Twig;

final class ComponentAttributes
{
    private const string ATTRIBUTE_PATTERN = '/(:?[\w-]+)="([^"]*)"/';

    /**
     * Formats the with clause from parsed attributes.
     *
     * @param string $attributesString Raw attribute string (e.g., 'label="Click" :disabled="isLoading"')
     * @return string The with clause or empty string
     */
    public static function formatWithClause(string $attributesString): string
    {
        $attributes = self::parseAttributes($attributesString);

        if ($attributes === []) {
            return '';
        }

        $withParts = [];

        foreach ($attributes as $key => $attribute) {
            if ($attribute['dynamic']) {
                $withParts[] = "{$key}: {$attribute['value']}";
                continue;
            }

            $withParts[] = "{$key}: '{$attribute['value']}'";
        }

        return ' with { ' . implode(', ', $withParts) . ' }';
    }

    /**
     * Parses component attributes from the raw attribute string.
     * Supports static (prop="value") and dynamic (:prop="expr") binding.
     *
     * @param string $attributesString Raw attribute string (e.g., 'label="Click" :disabled="isLoading"')
     * @return array<string, array{dynamic: bool, value: string}> Parsed attributes keyed by name
     */
    private static function parseAttributes(string $attributesString): array
    {
        $parsedAttributes = [];
        $matches = [];

        preg_match_all(self::ATTRIBUTE_PATTERN, $attributesString, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $isDynamic = str_starts_with($match[1], ':');
            $parsedAttributes[ltrim($match[1], ':')] = ['dynamic' => $isDynamic, 'value' => $match[2]];
        }

        return $parsedAttributes;
    }
}
