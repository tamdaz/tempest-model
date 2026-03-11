<?php

declare(strict_types=1);

namespace App\Twig\Extensions;

use Twig\Attribute\AsTwigFunction;

use function Tempest\Router\uri;
use function Tempest\Router\signed_uri;
use function Tempest\Router\temporary_signed_uri;
use function Tempest\Router\is_current_uri;

class RoutingExtension
{
    /**
     * Generate a URL for a given route.
     *
     * Examples:
     * - route('/home')
     * - route([HomeController::class, 'index'])
     * - route(HomeController::class) // for __invoke
     * - route([PostController::class, 'show'], id: 5)
     * 
     * @param array|string $action Controller class and method or URI
     * @param mixed ...$params Route parameters
     *
     * @return string
     */
    #[AsTwigFunction("route")]
    public static function route(array|string $action, mixed ...$params): string
    {
        return uri($action, ...$params);
    }

    /**
     * Generate a signed URL for a given route.
     * 
     * @param array|string $action Controller class and method or URI
     * @param mixed ...$params Route parameters
     *
     * @return string
     */
    #[AsTwigFunction("signed_route")]
    public static function signedRoute(array|string $action, mixed ...$params): string
    {
        return signed_uri($action, ...$params);
    }

    /**
     * Generate a temporary signed URL that expires after a duration.
     * 
     * @param array|string $action Controller class and method or URI
     * @param int $duration Duration in seconds
     * @param mixed ...$params Route parameters
     *
     * @return string
     */
    #[AsTwigFunction("temporary_signed_route")]
    public static function temporarySignedRoute(array|string $action, int $duration, mixed ...$params): string
    {
        return temporary_signed_uri($action, $duration, ...$params);
    }

    /**
     * Check if the current URL matches the given action.
     * 
     * @param array|string $action Controller class and method or URI
     * @param mixed ...$params Route parameters
     *
     * @return bool
     */
    #[AsTwigFunction("is_current_route")]
    public static function isCurrentRoute(array|string $action, mixed ...$params): bool
    {
        return is_current_uri($action, ...$params);
    }

    /**
     * Get the current path.
     * 
     * @return string
     */
    #[AsTwigFunction("current_path")]
    public static function currentPath(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }
}
