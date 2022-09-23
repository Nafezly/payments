<?php

namespace Nafezly\Payments;

use Illuminate\Support\ServiceProvider;

class NafezlyPaymentsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->configure();
        $this->registerMigrations();
        $this->registerPublishing();
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        
        return new PaymobPayment();
        /*$this->app->bind('nafezly-payments',function(){
             new PaymobPayment();
        });*/
    }

    /**
     * Setup the configuration for Cashier.
     *
     * @return void
     */
    protected function configure()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/nafezly-payments.php', 'nafezly-payments'
        );
    }

    /**
     * Register the package migrations.
     *
     * @return void
     */
    protected function registerMigrations()
    {
      
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
       
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    protected function registerPublishing()
    {
        $this->publishes([
            __DIR__.'/../config/nafezly-payments.php' => config_path('nafezly-payments.php'),
        ], 'nafezly-payments-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'nafezly-payments-migrations');
         
    }
}