<?php

namespace Laravoole;

use Illuminate\Support\ServiceProvider;

class LaravooleServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/laravoole.php' => config_path('laravoole.php'),
        ]);
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/laravoole.php', 'laravoole'
        );

        $this->commands([
            Commands\LaravooleCommand::class,
        ]);
    }
}
