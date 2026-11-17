<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Tests\TestCase;
use Webkul\CMS\Repositories\PageRepository;
use Webkul\Core\Repositories\ChannelRepository;
use Webkul\MXTechnology\Services\Storefront;

uses(TestCase::class);

beforeEach(function () {
    Storage::fake(config('filesystems.default'));
    $this->artisan('mx-theme:import-catalog', ['--no-interaction' => true])->assertSuccessful();
});

// ============================================================================
// Portable Theme Installation
// ============================================================================

it('resolves package view overrides while retaining native checkout fallbacks', function () {
    themes()->set('mx-technology');
    $finder = app('view.finder');
    $home = $finder->find('shop::home.index');
    $checkout = $finder->find('shop::checkout.cart.index');

    expect(realpath($home))->toBe(realpath(__DIR__.'/../../src/Resources/views/home/index.blade.php'))
        ->and($checkout)->not->toContain('MXTechnology/src/Resources/views/checkout');
});

it('resolves images and CMS links under a subdirectory application URL', function () {
    URL::forceRootUrl('https://example.test/store');
    URL::forceScheme('https');
    app()->forgetInstance(Storefront::class);
    $data = app(Storefront::class)->data();

    expect($data['groups'])->not->toBeEmpty();

    foreach ($data['groups'] as $group) {
        expect($group['image'])->toStartWith('https://example.test/store/');
    }

    foreach ($data['guides'] as $guide) {
        expect($guide['url'])->toStartWith('https://example.test/store/page/');
    }

    URL::forceRootUrl(null);
    URL::forceScheme(null);
});

it('hides links to default guides that have not been imported', function () {
    config(['mx-technology.guides' => [['group' => 'drones', 'title' => 'Missing', 'url' => '/page/mx-unpublished-guide']]]);
    app()->forgetInstance(Storefront::class);

    expect(app(Storefront::class)->data()['guides'])->toBeEmpty();
});

it('publishes and activates idempotently while preserving merchant settings', function () {
    $temporary = sys_get_temp_dir().'/mx-theme-install-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($temporary.'/public/themes/shop/default/build');
    File::ensureDirectoryExists($temporary.'/config');
    File::ensureDirectoryExists($temporary.'/bootstrap/cache');
    File::put($temporary.'/public/themes/shop/default/build/manifest.json', '{}');
    File::put($temporary.'/config/mx-technology.php', '<?php return ["accent" => "#112233"];');
    $oldPublic = public_path();
    $oldConfig = config_path();
    $publishes = ServiceProvider::$publishes;
    $groups = ServiceProvider::$publishGroups;
    $package = dirname(__DIR__, 2).'/src/';

    foreach (ServiceProvider::$publishes as &$paths) {
        foreach ($paths as $source => &$destination) {
            if (str_starts_with($source, $package)) {
                $destination = str_replace([$oldPublic, $oldConfig], [$temporary.'/public', $temporary.'/config'], $destination);
            }
        }
    }

    unset($paths, $destination);

    foreach (ServiceProvider::$publishGroups as &$paths) {
        foreach ($paths as $source => &$destination) {
            if (str_starts_with($source, $package)) {
                $destination = str_replace([$oldPublic, $oldConfig], [$temporary.'/public', $temporary.'/config'], $destination);
            }
        }
    }

    unset($paths, $destination);
    $this->app->usePublicPath($temporary.'/public');
    $this->app->useConfigPath($temporary.'/config');
    $this->app->useBootstrapPath($temporary.'/bootstrap');
    $channels = app(ChannelRepository::class);
    $channel = $channels->findOneWhere(['code' => 'default']);
    $before = [$channel->name, $channel->logo, $channel->favicon, $channel->locales->pluck('id')->all(), $channel->currencies->pluck('id')->all(), $channel->inventory_sources->pluck('id')->all()];

    try {
        $this->artisan('mx-theme:install', ['--activate' => true, '--no-interaction' => true])->assertSuccessful();
        $this->artisan('mx-theme:install', ['--activate' => true, '--no-interaction' => true])->assertSuccessful();
        $after = $channel->fresh();

        expect($after->theme)->toBe('mx-technology')
            ->and([$after->name, $after->logo, $after->favicon, $after->locales->pluck('id')->all(), $after->currencies->pluck('id')->all(), $after->inventory_sources->pluck('id')->all()])->toBe($before)
            ->and(File::get($temporary.'/config/mx-technology.php'))->toContain('#112233')
            ->and(is_file($temporary.'/public/themes/shop/mx-technology/theme.css'))->toBeTrue()
            ->and(is_file($temporary.'/public/themes/shop/mx-technology/catalog/dji-air-3s.webp'))->toBeTrue();
    } finally {
        ServiceProvider::$publishes = $publishes;
        ServiceProvider::$publishGroups = $groups;
        File::deleteDirectory($temporary);
    }
});

it('imports new buying-guide links against the subdirectory application URL', function () {
    $pages = app(PageRepository::class);
    $old = $pages->findByUrlKey('choose-your-drone');

    if ($old) {
        foreach ($old->translations as $translation) {
            $translation->url_key = 'retained-drone-guide-'.$old->id;
            $translation->save();
        }
    }

    URL::forceRootUrl('https://example.test/store');
    URL::forceScheme('https');

    try {
        $this->artisan('mx-theme:import-catalog', ['--no-interaction' => true])->assertSuccessful();
        $page = $pages->findByUrlKey('choose-your-drone');

        expect($page->html_content)->toContain('https://example.test/store/drones')
            ->not->toContain('href="/drones"');
    } finally {
        URL::forceRootUrl(null);
        URL::forceScheme(null);
    }
});
