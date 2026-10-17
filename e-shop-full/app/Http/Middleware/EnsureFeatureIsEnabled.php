<?php

namespace App\Http\Middleware;

use App\Services\PlatformSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a route when an administrator switched its feature off, e.g. `feature:shop_chat`.
 */
class EnsureFeatureIsEnabled
{
    public function __construct(private PlatformSettings $settings) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless($this->settings->featureEnabled($feature), 403, 'Cette fonctionnalité est désactivée sur la plateforme.');

        return $next($request);
    }
}
