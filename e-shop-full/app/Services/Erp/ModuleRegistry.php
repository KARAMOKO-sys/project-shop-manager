<?php

namespace App\Services\Erp;

use App\Models\Shop;
use App\Models\ShopModule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Installs and removes ERP applications on a shop, resolving dependencies the
 * way Odoo does: installing "Achats" also installs "Inventaire", and a module
 * another installed module relies on cannot be removed.
 */
class ModuleRegistry
{
    /**
     * @var array<int, list<string>>
     */
    private array $installedCache = [];

    public function __construct(
        private ActivityLogger $activity,
        private EventOutbox $outbox,
        private InventoryService $inventory,
    ) {}

    /**
     * @return array<string, array{name: string, summary: string, category: string, icon: string, depends: list<string>, core: bool}>
     */
    public function catalog(): array
    {
        return config('erp.modules');
    }

    public function exists(string $module): bool
    {
        return array_key_exists($module, $this->catalog());
    }

    /**
     * Keys of the modules active on the shop, core modules included.
     *
     * @return list<string>
     */
    public function installed(Shop $shop): array
    {
        return $this->installedCache[$shop->id] ??= array_values(array_unique([
            ...array_keys(array_filter($this->catalog(), fn (array $module): bool => $module['core'])),
            ...$shop->modules()->pluck('module')->all(),
        ]));
    }

    public function isInstalled(Shop $shop, string $module): bool
    {
        return in_array($module, $this->installed($shop), true);
    }

    /**
     * Install a module and, first, the modules it depends on.
     *
     * @return list<string> the modules that were newly installed
     */
    public function install(Shop $shop, string $module, ?User $user = null): array
    {
        $this->ensureExists($module);

        $toInstall = array_values(array_filter(
            $this->withDependencies($module),
            fn (string $key): bool => ! $this->isInstalled($shop, $key),
        ));

        DB::transaction(function () use ($shop, $toInstall, $user): void {
            foreach ($toInstall as $key) {
                ShopModule::create([
                    'shop_id' => $shop->id,
                    'module' => $key,
                    'installed_by' => $user?->id,
                    'installed_at' => now(),
                ]);

                $this->runInstaller($shop, $key);

                $this->activity->log($shop, 'module.installed', "Application « {$this->catalog()[$key]['name']} » installée.", properties: ['module' => $key]);
                $this->outbox->record('module.installed', ['shop_id' => $shop->id, 'module' => $key], $shop);
            }
        });

        $this->forget($shop);

        return $toInstall;
    }

    /**
     * Remove a module. Its data is kept so that a reinstallation restores it.
     */
    public function uninstall(Shop $shop, string $module): void
    {
        $this->ensureExists($module);

        if ($this->catalog()[$module]['core']) {
            throw ValidationException::withMessages(['module' => 'Cette application fait partie du socle et ne peut pas être désinstallée.']);
        }

        $dependents = array_values(array_filter(
            $this->installed($shop),
            fn (string $key): bool => in_array($module, $this->withDependencies($key), true) && $key !== $module,
        ));

        if ($dependents !== []) {
            $names = implode(', ', array_map(fn (string $key): string => $this->catalog()[$key]['name'], $dependents));

            throw ValidationException::withMessages(['module' => "Désinstallez d’abord : {$names}."]);
        }

        DB::transaction(function () use ($shop, $module): void {
            $shop->modules()->where('module', $module)->delete();

            $this->activity->log($shop, 'module.uninstalled', "Application « {$this->catalog()[$module]['name']} » désinstallée (données conservées).", properties: ['module' => $module]);
            $this->outbox->record('module.uninstalled', ['shop_id' => $shop->id, 'module' => $module], $shop);
        });

        $this->forget($shop);
    }

    /**
     * The module followed by all its transitive dependencies, dependencies first.
     *
     * @return list<string>
     */
    public function withDependencies(string $module): array
    {
        $resolved = [];

        $visit = function (string $key) use (&$visit, &$resolved): void {
            foreach ($this->catalog()[$key]['depends'] ?? [] as $dependency) {
                $visit($dependency);
            }

            if (! in_array($key, $resolved, true)) {
                $resolved[] = $key;
            }
        };

        $visit($module);

        return $resolved;
    }

    public function forget(Shop $shop): void
    {
        unset($this->installedCache[$shop->id]);
        $shop->unsetRelation('modules');
    }

    private function runInstaller(Shop $shop, string $module): void
    {
        match ($module) {
            'inventory' => $this->inventory->bootstrap($shop),
            default => null,
        };
    }

    private function ensureExists(string $module): void
    {
        if (! $this->exists($module)) {
            throw ValidationException::withMessages(['module' => 'Application inconnue.']);
        }
    }
}
