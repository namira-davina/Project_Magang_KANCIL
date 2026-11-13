<?php

namespace App\Providers;

use App\Services\BalesOtomatis\BalesOtomatisClient;
use App\Services\Llm\LlmClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BalesOtomatisClient::class, fn () => BalesOtomatisClient::fromConfig());
        $this->app->bind(LlmClient::class, fn () => LlmClient::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('webhook', fn (Request $request) => Limit::perMinute(300)->by($request->ip()));
    }
}
