<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AI\ClaudeLanguageModel;
use App\Services\AI\LanguageModel;
use App\Services\AI\NullLanguageModel;
use App\Services\PlatformSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(PlatformSettings::class);

        $this->app->scoped(LanguageModel::class, function (Application $app): LanguageModel {
            $config = config('shopai.ai');
            $settings = $app->make(PlatformSettings::class);

            if ($settings->get('ai.driver') === 'local' || blank($config['anthropic_api_key'])) {
                return new NullLanguageModel;
            }

            return new ClaudeLanguageModel(
                apiKey: $config['anthropic_api_key'],
                model: $settings->get('ai.model'),
                timeout: $config['timeout'],
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('moderate-products', fn (User $user): bool => $user->is_admin);
        Gate::define('manage-platform', fn (User $user): bool => $user->is_admin);

        RateLimiter::for('ai', fn (Request $request) => Limit::perMinute(
            (int) $this->app->make(PlatformSettings::class)->get('ai.requests_per_minute'),
        )->by($request->user()?->id ?: $request->ip()));
    }
}
