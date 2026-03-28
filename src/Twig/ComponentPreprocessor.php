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
     * Preprocesses the Twig template source, transforming component tags into directives.
     * Processes self-closing tags first, then block tags recursively.
     *
     * @param string $source The raw Twig template source code
     * @return string The preprocessed template with component tags transformed
     */
    public static function process(string $source): string
    {
        // Self-closing tags first (simpler, no nested content)
        $source = preg_replace_callback(
            '/<twig:([A-Z][A-Za-z0-9]*)(\s[^>]*)?\s*\/>/s',
            static fn ($m) => self::transformSelfClosing($m[1], $m[2] ?? ''),
            $source,
        );

        // Block tags (may nest: process recursively via string scanning)
        return self::processBlockTags($source);
    }

    /**
     * Transforms a self-closing component tag into a Twig include directive.
     *
     * @param string $componentName The component name (PascalCase)
     * @param string $attributesString Raw attribute string from the tag
     * @return string The Twig include directive
     */
    private static function transformSelfClosing(string $componentName, string $attributesString): string
    {
        $withVariables = self::buildWith(self::parseAttributes($attributesString));
        $withClause = $withVariables ? " with { {$withVariables} }" : '';

        return "{% include 'components/{$componentName}.html.twig'{$withClause} only %}";
    }

    /**
     * Scans the source, finds each outermost block component tag, recursively processes
     * its content, then replaces it with the equivalent Twig embed directive.
     *
     * @param string $source The Twig template source code to process
     * @return string The source with block component tags transformed to embed directives
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

            [$textBefore, $tagInfo, $closeTagPositions] = self::processOpeningTag(
                $source,
                $currentPos,
                $tagMatches
            );

            if ($closeTagPositions === null) {
                // Malformed: no closing tag found — leave as-is and move on
                $result .= $tagInfo['openingContent'];
                $currentPos = $tagInfo['contentStart'];
                continue;
            }

            $result .= $textBefore;
            $componentContent = substr($source, $tagInfo['contentStart'], $closeTagPositions[0] - $tagInfo['contentStart']);
            $processedContent = self::processBlockTags($componentContent);

            $result .= self::transformBlock($tagInfo['name'], $tagInfo['attributes'], $processedContent);
            $currentPos = $closeTagPositions[1];
        }

        return $result;
    }

    /**
     * Extracts and processes information from an opening component tag.
     * Returns text before tag, tag info, and closing tag positions.
     *
     * @param string $source The full template source
     * @param int $startPos The position to start extracting from
     * @param array $tagMatches The regex match result from preg_match
     * @return array{string, array, array|null} [textBefore, tagInfo, closeTagPositions]
     */
    private static function processOpeningTag(string $source, int $startPos, array $tagMatches): array
    {
        $openingTagStart = $tagMatches[0][1];
        $componentName = $tagMatches[1][0];
        $attributesString = $tagMatches[2][0] ?? '';
        $openingTagContent = $tagMatches[0][0];
        $contentStart = $openingTagStart + strlen($openingTagContent);

        $textBefore = substr($source, $startPos, $openingTagStart - $startPos);

        $tagInfo = [
            'name' => $componentName,
            'attributes' => $attributesString,
            'openingContent' => $openingTagContent,
            'contentStart' => $contentStart,
        ];

        $closeTagPositions = self::findClosingTag($source, $componentName, $contentStart);

        return [$textBefore, $tagInfo, $closeTagPositions];
    }

    /**
     * Finds the position of the closing tag matching the opening tag, accounting for nesting.
     * Uses a depth counter to handle nested same-name components.
     *
     * @param string $source The full template source
     * @param string $componentName The component name to match
     * @param int $searchFrom The character position to start searching from
     * @return array{int, int}|null [contentEnd, closingTagEnd] or null if no closing tag found
     */
    private static function findClosingTag(string $source, string $componentName, int $searchFrom): ?array
    {
        $nestingDepth = 1;
        $currentPos = $searchFrom;
        $sourceLength = strlen($source);

        while ($nestingDepth > 0 && $currentPos < $sourceLength) {
            [$nextOpeningPos, $nextClosingPos, $closingMatch] = self::findNextTags(
                $source,
                $componentName,
                $currentPos
            );

            if ($nextClosingPos === null) {
                return null;
            }

            if ($nextOpeningPos !== null && $nextOpeningPos < $nextClosingPos) {
                $nestingDepth++;
                $currentPos = $nextOpeningPos + 1;
            } else {
                $nestingDepth--;
                if ($nestingDepth === 0) {
                    return [$nextClosingPos, $nextClosingPos + strlen($closingMatch[0][0])];
                }
                $currentPos = $nextClosingPos + 1;
            }
        }

        return null;
    }

    /**
     * Searches for the next opening and closing tags for a given component name.
     * Returns their positions and the closing tag match.
     *
     * @param string $source The template source
     * @param string $componentName The component name to search for
     * @param int $currentPos The position to start searching from
     * @return array{int|null, int|null, array|null} [openingPos, closingPos, closingMatch]
     */
    private static function findNextTags(
        string $source,
        string $componentName,
        int $currentPos
    ): array {
        $nextOpeningPos = null;
        $nextClosingPos = null;
        $closingMatch = null;

        if (preg_match("/<twig:{$componentName}[\s>]/", $source, $openingMatch, PREG_OFFSET_CAPTURE, $currentPos)) {
            $nextOpeningPos = $openingMatch[0][1];
        }

        if (preg_match("/<\\/twig:{$componentName}>/", $source, $closingTagMatch, PREG_OFFSET_CAPTURE, $currentPos)) {
            $nextClosingPos = $closingTagMatch[0][1];
            $closingMatch = $closingTagMatch;
        }

        return [$nextOpeningPos, $nextClosingPos, $closingMatch];
    }

    /**
     * Transforms a block component tag into a Twig embed directive with named blocks.
     * Extracts <twig:block name="..."> slots and converts remaining content to default block.
     *
     * @param string $componentName The component name (PascalCase)
     * @param string $attributesString Raw attribute string from the opening tag
     * @param string $content The inner content (already recursively processed)
     * @return string The Twig embed directive with block definitions
     */
    private static function transformBlock(string $componentName, string $attributesString, string $content): string
    {
        $withVariables = self::buildWith(self::parseAttributes($attributesString));
        $withClause = $withVariables ? " with { {$withVariables} }" : '';

        $blockDefinitions = self::extractNamedBlocks($content);

        return "{% embed 'components/{$componentName}.html.twig'{$withClause} %}{$blockDefinitions}{% endembed %}";
    }

    /**
     * Extracts named blocks from component content and builds block definitions.
     * Removes <twig:block name="..."> elements and generates Twig block definitions.
     * Remaining content becomes the default "content" block.
     *
     * @param string $content The component's inner content (modified by reference)
     * @return string The concatenated Twig block definitions
     */
    private static function extractNamedBlocks(string &$content): string
    {
        $blockDefinitions = '';

        $content = preg_replace_callback(
            '/<twig:block\s+name="([\w-]+)">(.*?)<\/twig:block>/s',
            static function ($matches) use (&$blockDefinitions): string {
                $blockDefinitions .= "{% block {$matches[1]} %}{$matches[2]}{% endblock %}";
                return '';
            },
            $content,
        );

        // Remaining non-empty content becomes the default "content" block
        if (trim($content) !== '') {
            $blockDefinitions .= "{% block content %}{$content}{% endblock %}";
        }

        return $blockDefinitions;
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
        preg_match_all('/(:?[\w-]+)="([^"]*)"/', $attributesString, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $isDynamic = str_starts_with($match[1], ':');
            $parsedAttributes[ltrim($match[1], ':')] = ['dynamic' => $isDynamic, 'value' => $match[2]];
        }

        return $parsedAttributes;
    }

    /**
     * Builds a Twig variable binding string from parsed attributes.
     * Static attributes are quoted (key: 'value'), dynamic attributes are unquoted (key: expr).
     *
     * @param array<string, array{dynamic: bool, value: string}> $attributes Parsed attributes
     * @return string The formatted variable binding string for Twig's "with" clause
     */
    private static function buildWith(array $attributes): string
    {
        if ($attributes === []) {
            return '';
        }

        $withParts = array_map(
            static fn ($key, $attribute) => $attribute['dynamic'] ? "{$key}: {$attribute['value']}" : "{$key}: '{$attribute['value']}'",
            array_keys($attributes),
            $attributes,
        );

        return implode(', ', $withParts);
    }
}
