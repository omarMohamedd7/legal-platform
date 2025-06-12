<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->app->register(ResourceServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceRootUrl(config('app.url'));

        // Make sure API resources don't wrap data in a 'data' key by default
        // Our BaseResource takes care of the wrapping
        JsonResource::withoutWrapping();

        // Handle authentication exceptions for API requests
        $this->app->singleton(
            \Illuminate\Auth\AuthenticationException::class,
            function ($app) {
                return new \Illuminate\Auth\AuthenticationException(
                    'Unauthenticated. Please login to continue.'
                );
            }
        );
    }
}
