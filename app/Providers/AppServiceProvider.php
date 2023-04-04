<?php

namespace App\Providers;

use App\Services\ReddyBot\AgentsBotService;
use App\Services\ReddyBot\LoggerService;
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
        $this->app->singleton(LoggerService::class, function ($app) {
            return new LoggerService();
        });

        $this->app->singleton(AgentsBotService::class, function ($app) {
            return new AgentsBotService();
        });
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
