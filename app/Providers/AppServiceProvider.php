<?php

namespace App\Providers;

use App\Services\RevenueMonster\RevenueMonsterClient;
use App\Services\RevenueMonster\RevenueMonsterService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Build the RM client from config (keys, sandbox flag) rather than
        // letting the container autowire its optional dependencies with nulls.
        $this->app->singleton(RevenueMonsterClient::class, fn () => new RevenueMonsterClient());
        $this->app->singleton(
            RevenueMonsterService::class,
            fn ($app) => new RevenueMonsterService($app->make(RevenueMonsterClient::class))
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
