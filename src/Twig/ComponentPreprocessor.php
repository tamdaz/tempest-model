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
    private const string SELF_CLOSING_COMPONENT_PATTERN = '/<twig:([A-Z][A-Za-z0-9]*)(\s[^>]*)?\s*\/>/s';
    private const string OPENING_COMPONENT_PATTERN = '/<twig:([A-Z][A-Za-z0-9]*)(\s[^>]*)?>/';
    private const string OPENING_COMPONENT_SEARCH_PATTERN = '/<twig:%s[\s>]/';
    private const string CLOSING_COMPONENT_SEARCH_PATTERN = '/<\/twig:%s>/';
    private const string NAMED_BLOCK_PATTERN = '/<twig:block\s+name="([\w-]+)">(.*?)<\/twig:block>/s';

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
        $processedSource = preg_replace_callback(
            self::SELF_CLOSING_COMPONENT_PATTERN,
            static function (array $matches): string {
                $attributesString = '';
                if (array_key_exists(2, $matches)) {
                    $attributesString = $matches[2];
                }

                return self::transformSelfClosing($matches[1], $attributesString);
            },
            $source,
        );
        if ($processedSource !== null) {
            $source = $processedSource;
        }

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
        $withClause = ComponentAttributes::formatWithClause($attributesString);

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
            $tagMatches = [];

            // Find the next opening block tag (PascalCase = component, not <twig:block …>)
            if (preg_match(self::OPENING_COMPONENT_PATTERN, $source, $tagMatches, PREG_OFFSET_CAPTURE, $currentPos) !== 1) {
                $result .= substr($source, $currentPos);
                break;
            }

            /** @var array{0: array{0: string, 1: int}, 1: array{0: string, 1: int}, 2?: array{0: string, 1: int}} $tagMatches */
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
     * @return array{0: string, 1: array{name: string, attributes: string, openingContent: string, contentStart: int}, 2: array{0: int, 1: int}|null} [textBefore, tagInfo, closeTagPositions]
     */
    private static function processOpeningTag(string $source, int $startPos, array $tagMatches): array
    {
        /** @var array{0: array{0: string, 1: int}, 1: array{0: string, 1: int}, 2?: array{0: string, 1: int}} $tagMatches */
        $openingTagStart = (int) $tagMatches[0][1];
        $componentName = $tagMatches[1][0];

        if (array_key_exists(2, $tagMatches) && array_key_exists(0, $tagMatches[2])) {
            $attributesString = $tagMatches[2][0];
        } else {
            $attributesString = '';
        }
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
                    /** @var array{0: array{0: string, 1: int}} $closingMatch */
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
    private static function findNextTags(string $source, string $componentName, int $currentPos): array {
        $nextOpeningPos = null;
        $nextClosingPos = null;
        $closingMatch = null;
        $openingMatch = [];
        $closingTagMatch = [];

        $openingPattern = sprintf(self::OPENING_COMPONENT_SEARCH_PATTERN, $componentName);
        $closingPattern = sprintf(self::CLOSING_COMPONENT_SEARCH_PATTERN, $componentName);

        if (preg_match($openingPattern, $source, $openingMatch, PREG_OFFSET_CAPTURE, $currentPos) === 1) {
            $nextOpeningPos = (int) $openingMatch[0][1];
        }

        if (preg_match($closingPattern, $source, $closingTagMatch, PREG_OFFSET_CAPTURE, $currentPos) === 1) {
            $nextClosingPos = (int) $closingTagMatch[0][1];
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
        $withClause = ComponentAttributes::formatWithClause($attributesString);

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

        $updatedContent = preg_replace_callback(
            self::NAMED_BLOCK_PATTERN,
            static function ($matches) use (&$blockDefinitions): string {
                $blockDefinitions .= "{% block {$matches[1]} %}{$matches[2]}{% endblock %}";
                return '';
            },
            $content,
        );

        if ($updatedContent !== null) {
            $content = $updatedContent;
        }

        // Remaining non-empty content becomes the default "content" block
        if (trim($content) !== '') {
            $blockDefinitions .= "{% block content %}{$content}{% endblock %}";
        }

        return $blockDefinitions;
    }
}
