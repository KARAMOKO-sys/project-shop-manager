<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\Erp\ModuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The "Applications" screen: install and remove ERP modules on a shop.
 */
class ModuleController extends Controller
{
    public function __construct(private ModuleRegistry $modules) {}

    public function index(Shop $shop): JsonResponse
    {
        return response()->json(['data' => $this->catalogFor($shop)]);
    }

    public function store(Request $request, Shop $shop, string $module): JsonResponse
    {
        $installed = $this->modules->install($shop, $module, $request->user());

        return response()->json([
            'data' => $this->catalogFor($shop),
            'installed' => $installed,
        ], $installed === [] ? 200 : 201);
    }

    public function destroy(Shop $shop, string $module): JsonResponse
    {
        $this->modules->uninstall($shop, $module);

        return response()->json(['data' => $this->catalogFor($shop)]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function catalogFor(Shop $shop): array
    {
        $installed = $this->modules->installed($shop);
        $installedAt = $shop->modules()->pluck('installed_at', 'module');
        $catalog = [];

        foreach ($this->modules->catalog() as $key => $module) {
            $catalog[] = [
                'key' => $key,
                'name' => $module['name'],
                'summary' => $module['summary'],
                'category' => $module['category'],
                'icon' => $module['icon'],
                'depends' => $module['depends'],
                'core' => $module['core'],
                'installed' => in_array($key, $installed, true),
                'installed_at' => $installedAt[$key] ?? null,
                'required_by' => array_values(array_filter(
                    $installed,
                    fn (string $other): bool => $other !== $key && in_array($key, $this->modules->withDependencies($other), true),
                )),
            ];
        }

        return $catalog;
    }
}
