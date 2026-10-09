<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AI\ClaudeLanguageModel;
use App\Services\AI\LanguageModel;
use App\Services\AI\NullLanguageModel;
use Illuminate\Cache\RateLimiting\Limit;
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
        $this->app->singleton(LanguageModel::class, function (): LanguageModel {
            $config = config('shopai.ai');

            if ($config['driver'] === 'local' || blank($config['anthropic_api_key'])) {
                return new NullLanguageModel;
            }

            return new ClaudeLanguageModel(
                apiKey: $config['anthropic_api_key'],
                model: $config['model'],
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

        RateLimiter::for('ai', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
    }
}
