<?php
namespace devebk\payme;
use Illuminate\Support\ServiceProvider;

class PaymeServiceProvider extends ServiceProvider{
    public function boot(){

        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([

                __DIR__.'/config/config.php' => config_path('PaymeConfig.php')
            ], 'Payme-uz-configuration');

            $this->mergeConfigFrom(
                __DIR__ . '/config/config.php', 'PaymeConfig'
            );

        }
    }
    public function register()
    {
        config([
            'config/PaymeConfig.php',
        ]);
    }


}
