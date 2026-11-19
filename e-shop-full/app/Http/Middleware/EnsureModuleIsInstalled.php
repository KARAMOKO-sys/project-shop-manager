<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use App\Services\Erp\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the routes of an ERP application the shop has not installed, e.g. `module:inventory`.
 */
class EnsureModuleIsInstalled
{
    public function __construct(private ModuleRegistry $modules) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $shop = $request->route('shop');

        abort_unless($shop instanceof Shop && $this->modules->isInstalled($shop, $module), 403, 'Installez d’abord cette application pour votre boutique.');

        return $next($request);
    }
}
