<?php

declare(strict_types=1);

namespace App\Twig;

/**
 * Preprocesses Twig template source to transform HTML-like component tags
 * into native Twig include/embed directives.
 *
 * Supported syntax:
 *
 *   Self-closing (maps to {% include %}):
 *     <twig:Button label="Click" :disabled="isLoading" />
 *
 *   With a default content slot (maps to {% embed %}):
 *     <twig:Alert type="warning">Message here</twig:Alert>
 *
 *   With named slots (maps to {% embed %} + {% block %}):
 *     <twig:Card title="Hello">
 *         <twig:block name="header"><h2>Header</h2></twig:block>
 *         <p>Default slot content</p>
 *     </twig:Card>
 *
 * Attribute binding:
 *   - Static:  prop="value"    → { prop: 'value' }
 *   - Dynamic: :prop="expr"    → { prop: expr }    (Twig expression, no quotes added)
 *
 * Components are resolved from the templates/components/ directory:
 *   <twig:Button /> → {% include 'components/Button.html.twig' … %}
 *
 * Component names must be PascalCase to distinguish them from HTML tags.
 * Named slots use lowercase <twig:block name="…"> to avoid being treated as components.
 */
final class ComponentPreprocessor
{
    /**
     * Preprocesses the Twig template source, transforming component tags into Twig directives.
     *
     * Processes in two stages:
     * 1. Self-closing tags → {% include %} directives
     * 2. Block tags → {% embed %} directives with named slots
     *
     * @param string $source The raw Twig template source code
     * @return string The preprocessed template with component tags transformed
     */
    public static function process(string $source): string
    {
        // Self-closing tags first (simpler, no nested content)
        $source = preg_replace_callback(
            '/<twig:([A-Z][A-Za-z0-9]*)(\s[^>]*)?\s*\/>/s',
            fn($m) => self::transformSelfClosing($m[1], $m[2] ?? ''),
            $source,
        );

        // Block tags (may nest: process recursively via string scanning)
        return self::processBlockTags($source);
    }

    /**
     * Transforms a self-closing component tag into a Twig include directive.
     *
     * Example:
     *   Input:  <twig:Button label="Click" :disabled="isLoading" />
     *   Output: {% include 'components/Button.html.twig' with { label: 'Click', disabled: isLoading } only %}
     *
     * @param string $componentName The name of the component (PascalCase)
     * @param string $attributesString Raw attribute string from the tag (e.g., ' label="Click" :disabled="isLoading"')
     * @return string The Twig include directive
     */
    private static function transformSelfClosing(string $componentName, string $attributesString): string
    {
        $withVariables = self::buildWith(self::parseAttributes($attributesString));
        $withClause = $withVariables ? " with { {$withVariables} }" : '';

        return "{% include 'components/{$componentName}.html.twig'{$withClause} only %}";
    }

    /**
     * Scans the source left-to-right, finds each outermost block component tag,
     * recursively processes its content, then replaces the whole span with the
     * equivalent Twig embed directive.
     *
     * Uses character position tracking to handle nested components of the same name.
     * Does not use regex replacement to preserve exact positioning and nested context.
     *
     * @param string $source The Twig template source code to process
     * @return string The source with all block component tags transformed to embed directives
     */
    private static function processBlockTags(string $source): string
    {
        $result = '';
        $currentPos = 0;
        $sourceLength = strlen($source);

        while ($currentPos < $sourceLength) {
            // Find the next opening block tag (PascalCase = component, not <twig:block …>)
            if (!preg_match('/<twig:([A-Z][A-Za-z0-9]*)(\s[^>]*)?>/', $source, $tagMatches, PREG_OFFSET_CAPTURE, $currentPos)) {
                $result .= substr($source, $currentPos);
                break;
            }

            $openingTagStart = $tagMatches[0][1];
            $componentName = $tagMatches[1][0];
            $attributesString = $tagMatches[2][0] ?? '';
            $openingTagContent = $tagMatches[0][0];
            $contentStart = $openingTagStart . strlen($openingTagContent);

            // Append everything before this tag as-is
            $result .= substr($source, $currentPos, $openingTagStart - $currentPos);

            // Find the matching closing tag, respecting nested same-name components
            [$contentEnd, $closingTagEnd] = self::findClosingTag($source, $componentName, $contentStart);

            if ($contentEnd === null) {
                // Malformed: no closing tag found — leave as-is and move on
                $result .= $openingTagContent;
                $currentPos = $contentStart;

                continue;
            }

            $componentContent = substr($source, $contentStart, $contentEnd - $contentStart);

            // Recurse into the content before transforming
            $processedContent = self::processBlockTags($componentContent);

            $result .= self::transformBlock($componentName, $attributesString, $processedContent);
            $currentPos = $closingTagEnd;
        }

        return $result;
    }

    /**
     * Finds the position of the closing tag `</twig:ComponentName>` that matches the
     * opening tag at $searchFrom, accounting for nested same-name components.
     *
     * Uses a depth counter to handle nested tags:
     * - Increments depth when an opening tag is found
     * - Decrements depth when a closing tag is found
     * - Returns positions when depth reaches 0
     *
     * @param string $source The full template source
     * @param string $componentName The name of the component to match (used in closing tag)
     * @param int $searchFrom The character position to start searching from (usually after opening tag)
     * @return array{int|null, int|null} [contentEnd, closingTagEnd] where:
     *         - contentEnd is the position right before the closing tag
     *         - closingTagEnd is the position after the closing tag ends
     *         - [null, null] if no matching closing tag is found
     */
    private static function findClosingTag(string $source, string $componentName, int $searchFrom): array
    {
        $nestingDepth = 1;
        $currentPos = $searchFrom;
        $sourceLength = strlen($source);

        while ($nestingDepth > 0 && $currentPos < $sourceLength) {
            $nextOpeningTagPos = PHP_INT_MAX;
            $nextClosingTagPos = PHP_INT_MAX;

            if (preg_match("/<twig:{$componentName}[\s>]/", $source, $openingTagMatch, PREG_OFFSET_CAPTURE, $currentPos)) {
                $nextOpeningTagPos = $openingTagMatch[0][1];
            }

            if (preg_match("/<\\/twig:{$componentName}>/", $source, $closingTagMatch, PREG_OFFSET_CAPTURE, $currentPos)) {
                $nextClosingTagPos = $closingTagMatch[0][1];
            }

            if ($nextClosingTagPos === PHP_INT_MAX) {
                return [null, null]; // No closing tag
            }

            if ($nextOpeningTagPos < $nextClosingTagPos) {
                $nestingDepth++;
                $currentPos = $nextOpeningTagPos + 1;
            } else {
                $nestingDepth--;
                $currentPos = $nextClosingTagPos + 1;

                if ($nestingDepth === 0) {
                    return [$nextClosingTagPos, $nextClosingTagPos + strlen($closingTagMatch[0][0])];
                }
            }
        }

        return [null, null];
    }

    /**
     * Transforms a block component tag into a Twig embed directive with named blocks.
     *
     * Extracts named slots from the component content using the <twig:block name="..."> syntax
     * and converts them to Twig block definitions. Any remaining content becomes the default
     * "content" block.
     *
     * Example:
     *   Input:
     *     <twig:Card title="Hello">
     *         <twig:block name="header"><h2>Header</h2></twig:block>
     *         <p>Default content</p>
     *     </twig:Card>
     *   Output:
     *     {% embed 'components/Card.html.twig' with { title: 'Hello' } %}
     *         {% block header %}<h2>Header</h2>{% endblock %}
     *         {% block content %}<p>Default content</p>{% endblock %}
     *     {% endembed %}
     *
     * @param string $componentName The name of the component (PascalCase)
     * @param string $attributesString Raw attribute string from the opening tag
     * @param string $content The inner content of the component tag (already recursively processed)
     * @return string The Twig embed directive with all block definitions
     */
    private static function transformBlock(string $componentName, string $attributesString, string $content): string
    {
        $withVariables = self::buildWith(self::parseAttributes($attributesString));
        $withClause = $withVariables ? " with { {$withVariables} }" : '';
        $blockDefinitions = '';

        // Extract named slots: <twig:block name="foo">…</twig:block>
        $content = preg_replace_callback(
            '/<twig:block\s+name="([\w-]+)">(.*?)<\/twig:block>/s',
            function ($matches) use (&$blockDefinitions): string {
                $blockDefinitions .= "{% block {$matches[1]} %}{$matches[2]}{% endblock %}";

                return '';
            },
            $content,
        );

        // Remaining non-empty content becomes the default "content" block
        if (trim($content) !== '') {
            $blockDefinitions .= "{% block content %}{$content}{% endblock %}";
        }

        return "{% embed 'components/{$componentName}.html.twig'{$withClause} %}{$blockDefinitions}{% endembed %}";
    }

    /**
     * Parses component attributes from the raw attribute string.
     *
     * Supports static and dynamic binding:
     * - Static:  prop="value"    → key='prop', dynamic=false, value='value'
     * - Dynamic: :prop="expr"    → key='prop', dynamic=true,  value='expr' (Twig expression)
     *
     * The leading colon in dynamic attributes is stripped from the key.
     *
     * @param string $attributesString Raw attribute string (e.g., 'label="Click" :disabled="isLoading"')
     * @return array<string, array{dynamic: bool, value: string}> Parsed attributes keyed by attribute name.
     *         Each value is an associative array with 'dynamic' and 'value' keys.
     */
    private static function parseAttributes(string $attributesString): array
    {
        $parsedAttributes = [];
        preg_match_all('/(:?[\w-]+)="([^"]*)"/', $attributesString, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $isDynamic = str_starts_with($match[1], ':');
            $parsedAttributes[ltrim($match[1], ':')] = ['dynamic' => $isDynamic, 'value' => $match[2]];
        }

        return $parsedAttributes;
    }

    /**
     * Builds a Twig variable binding string from parsed attributes.
     *
     * Converts parsed attributes into Twig "with" clause format:
     * - Static attributes are quoted:   key: 'value'
     * - Dynamic attributes are unquoted: key: expression
     *
     * Returns empty string if attributes array is empty.
     *
     * Example:
     *   Input:  ['label' => ['dynamic' => false, 'value' => 'Click'], 
     *            'count' => ['dynamic' => true, 'value' => 'items.length']]
     *   Output: "label: 'Click', count: items.length"
     *
     * @param array<string, array{dynamic: bool, value: string}> $attributes Parsed attributes from parseAttributes()
     * @return string The formatted variable binding string for use in Twig's "with" clause
     */
    private static function buildWith(array $attributes): string
    {
        if ($attributes === []) {
            return '';
        }

        $withParts = array_map(
            fn($key, $attribute) => $attribute['dynamic'] ? "{$key}: {$attribute['value']}" : "{$key}: '{$attribute['value']}'",
            array_keys($attributes),
            $attributes,
        );

        return implode(', ', $withParts);
    }
}
