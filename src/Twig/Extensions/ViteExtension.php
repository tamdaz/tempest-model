<?php

declare(strict_types=1);

namespace App\Twig\Extensions;

use Tempest\Vite\Exceptions\ViteException;
use Twig\Attribute\AsTwigFunction;

use function Tempest\root_path;
use function Tempest\Vite\get_tags;

/**
 * The Twig extension for Vite integration.
 */
class ViteExtension
{
    private static ?array $manifest = null;

    /**
     * Generates <script> and <link> tags for Vite entrypoints.
     *
     * Delegates to the native Tempest/Vite integration, which automatically handles
     * development mode (HMR via vite-plugin-tempest) and production (hashed manifest).
     * No tags are emitted in test environments.
     *
     * @param string ...$entries Entrypoint paths.
     * @return string Rendered HTML tags for the entrypoints.
     */
    #[AsTwigFunction('vite_tags', isSafe: ['html'])]
    public static function viteTags(string ...$entries): string
    {
        try {
            $entrypoints = array_values(array_filter($entries));
            if ($entrypoints === []) {
                $entrypoints = null;
            }

            $tags = get_tags($entrypoints);
        } catch (ViteException $e) {
            return '';
        }

        return implode("\n    ", $tags);
    }

    /**
     * Returns the public URL of an asset hashed in the Vite manifest.
     *
     * @param string $path Asset path relative to the project root.
     * @return string Public URL for the asset.
     */
    #[AsTwigFunction('vite_asset')]
    public static function viteAsset(string $path): string
    {
        $manifest = self::getManifest();

        if (isset($manifest[$path])) {
            return '/build/' . $manifest[$path]['file'];
        }

        return '/build/' . $path;
    }

    /**
     * Loads and caches the Vite manifest file.
     *
     * @return array The manifest data. If the manifest file is missing or invalid,
     * it returns an empty array.
     */
    private static function getManifest(): array
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }

        $manifestPath = root_path('public/build/manifest.json');

        if (!file_exists($manifestPath)) {
            return self::$manifest = [];
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        if (!is_array($manifest)) {
            $manifest = [];
        }

        self::$manifest = $manifest;

        return self::$manifest;
    }
}
