<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\RateLimiterService;

class RateLimitServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(RateLimiterService::class, function ($app) {
            return new RateLimiterService();
        });
        
        $this->app->alias(RateLimiterService::class, 'rate_limit');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
} 