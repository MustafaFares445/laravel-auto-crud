<?php

namespace Mrmarchone\LaravelAutoCrud;

use Illuminate\Support\ServiceProvider;
use Mrmarchone\LaravelAutoCrud\Console\Commands\GenerateAutoCrudCommand;
use Mrmarchone\LaravelAutoCrud\Console\Commands\GenerateBulkEndpointsCommand;
use Mrmarchone\LaravelAutoCrud\Console\Commands\GenerateTestsCommand;
use Mrmarchone\LaravelAutoCrud\Console\Commands\PublishAIModelRulesCommand;
use Mrmarchone\LaravelAutoCrud\Console\Commands\PublishTranslationsCommand;
use Mrmarchone\LaravelAutoCrud\Services\TableColumnsService;

class LaravelAutoCrudServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TableColumnsService::class, function ($app) {
            return new TableColumnsService;
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../Config/laravel_auto_crud.php' => config_path('laravel_auto_crud.php'),
        ], 'auto-crud-config');

        // Load package translations
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'laravel-auto-crud');

        // Publish ResponseMessages translations
        $this->publishes([
            __DIR__.'/../resources/lang' => lang_path('vendor/laravel-auto-crud'),
        ], 'auto-crud-translations');

        // Boot any package services here
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateAutoCrudCommand::class,
                GenerateBulkEndpointsCommand::class,
                GenerateTestsCommand::class,
                PublishTranslationsCommand::class,
                PublishAIModelRulesCommand::class,
            ]);
        }
    }
}
