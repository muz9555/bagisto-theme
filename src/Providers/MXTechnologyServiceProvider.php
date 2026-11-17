<?php

namespace Webkul\MXTechnology\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Webkul\MXTechnology\Console\ImportCatalog;
use Webkul\MXTechnology\Console\InstallTheme;
use Webkul\MXTechnology\Services\Storefront;
use Webkul\Theme\ViewRenderEventManager;

class MXTechnologyServiceProvider extends ServiceProvider
{
    /**
     * Register the theme without replacing Bagisto's assets or checkout views.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/theme.php', 'mx-technology');
        $this->app->scoped(Storefront::class);
        $default = config('themes.shop.default', []);

        config(['themes.shop.mx-technology' => array_replace($default, [
            'name' => 'MX Technology',
            'views_path' => 'resources/themes/mx-technology/views',
            'views_namespace' => 'mx-technology',
            'parent' => 'default',
        ])]);
    }

    /**
     * Publish standalone assets and supply live catalog content to theme views.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'mx-technology');
        $this->commands([InstallTheme::class, ImportCatalog::class]);
        $this->publishes([__DIR__.'/../Resources/views' => resource_path('themes/mx-technology/views')], 'mx-technology-views');
        $this->publishes([__DIR__.'/../Resources/public' => public_path('themes/shop/mx-technology')], 'mx-technology-assets');
        $this->publishes([__DIR__.'/../Config/theme.php' => config_path('mx-technology.php')], 'mx-technology-config');

        View::composer([
            'shop::home.index',
            'shop::components.layouts.header.index',
            'shop::components.layouts.footer.index',
        ], function ($view) {
            if (themes()->current()?->code === 'mx-technology') {
                $view->with('mx', app(Storefront::class)->data());
            }
        });

        Event::listen('bagisto.shop.layout.head.after', function (ViewRenderEventManager $manager) {
            if (themes()->current()?->code === 'mx-technology') {
                $manager->addTemplate('mx-technology::partials.assets');
            }
        });
    }
}
