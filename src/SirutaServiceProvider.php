<?php

namespace adrianciacob\Siruta;

use adrianciacob\Siruta\Console\SirutaImportCommand;
use adrianciacob\Siruta\Services\SirutaService;
use Illuminate\Support\ServiceProvider;

class SirutaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/siruta.php',
            'siruta'
        );

        $this->app->singleton(
            SirutaService::class,
            fn() => new SirutaService()
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(
            __DIR__ . '/../database/migrations'
        );

        $this->publishes([
            __DIR__ . '/../config/siruta.php' => config_path('siruta.php'),
        ], 'siruta-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SirutaImportCommand::class,
            ]);
        }
    }
}
