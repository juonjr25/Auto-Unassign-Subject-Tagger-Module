<?php

namespace Modules\AutoUnassign\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Events\CustomerReplied;
use Modules\AutoUnassign\Listeners\UpdateConversationStatus;
use Illuminate\Database\Eloquent\Factory;

class AutoUnassignServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerConfig();
        $this->registerViews();
        $this->registerFactories();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->hooks();

        // Tambahkan ini agar folder public link ke /public/modules/AutoUnassign
        $source = base_path('Modules/AutoUnassign/public');
        $target = public_path('modules/autounassign');
        if (!file_exists($target)) {
            try {
                symlink($source, $target);
            } catch (\Exception $e) {
                \File::copyDirectory($source, $target);
            }
        }
    }

    /**
     * Module hooks.
     *
     * Tempat daftarkan event listener.
     *
     * @return void
     */
    public function hooks()
    {
        Event::listen(CustomerReplied::class, UpdateConversationStatus::class);

        // Tambahkan JS ke halaman conversation
        \Eventy::addFilter('javascripts', function ($javascripts) {
            $javascripts[] = \Module::getPublicPath('autounassign') . '/js/custom_reply.js';
            \Log::info('[AutoUnassign] JS Injected'); // opsional log
            return $javascripts;
        });
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerTranslations();
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('autounassign.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'autounassign'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/autounassign');

        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ],'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/autounassign';
        }, \Config::get('view.paths')), [$sourcePath]), 'autounassign');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $this->loadJsonTranslationsFrom(__DIR__ .'/../Resources/lang');
    }

    /**
     * Register an additional directory of factories.
     *
     * @return void
     */
    public function registerFactories()
    {
        if (! app()->environment('production')) {
            app(Factory::class)->load(__DIR__ . '/../Database/factories');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }

     /**
     * Return module icon path.
     *
     * @return string
     */
    public function getIcon()
    {
        return 'modules/autounassign/img/icon.png';
    }
}
