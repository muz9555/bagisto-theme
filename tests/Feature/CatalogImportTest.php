<?php

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Webkul\Core\Repositories\ChannelRepository;
use Webkul\Core\Repositories\CurrencyRepository;
use Webkul\Product\Repositories\ProductRepository;

uses(TestCase::class);

beforeEach(function () {
    Storage::fake(config('filesystems.default'));
});

// ============================================================================
// Manufacturer Catalog Import
// ============================================================================

it('creates localized product details at regular prices with zero purchasable stock', function () {
    $products = app(ProductRepository::class);
    $old = $products->findOneWhere(['sku' => 'MX-DJI-MINI-4-PRO']);

    if ($old) {
        $products->update(['sku' => 'TEMP-'.$old->id, 'url_key' => 'temp-'.$old->id, 'locale' => core()->getDefaultLocaleCodeFromDefaultChannel()], $old->id, ['sku', 'url_key']);
    }

    $this->artisan('mx-theme:import-catalog', ['--no-interaction' => true])->assertSuccessful();
    $product = $products->findOneWhere(['sku' => 'MX-DJI-MINI-4-PRO']);

    expect($product->name)->toBe('DJI Mini 4 Pro (DJI RC-N2)')
        ->and($product->url_key)->toBe('dji-mini-4-pro')
        ->and((float) $product->price)->toBe(759.0)
        ->and($product->inventories->sum('qty'))->toBe(0)
        ->and($product->getTypeInstance()->isSaleable())->toBeFalse()
        ->and($product->description)->toContain('manufacturer', 'https://www.dji.com/')
        ->and($product->images)->toHaveCount(1)
        ->and($product->categories->pluck('slug')->all())->toContain('drones', 'camera-drones')
        ->and($product->categories->pluck('display_mode')->unique()->all())->toBe(['products_and_description']);
    Storage::assertExists($product->images->first()->path);

    $snapshot = $product->attribute_values->toJson();
    $this->artisan('mx-theme:import-catalog', ['--no-interaction' => true])->assertSuccessful();
    expect($product->fresh()->attribute_values->toJson())->toBe($snapshot)
        ->and($products->findWhere(['sku' => 'MX-DJI-MINI-4-PRO']))->toHaveCount(1);
});

it('leaves prices and inventory untouched during a preview', function () {
    $before = app(ProductRepository::class)->all()->count();
    $this->artisan('mx-theme:import-catalog', ['--dry-run' => true, '--no-interaction' => true])->assertSuccessful();
    expect(app(ProductRepository::class)->all()->count())->toBe($before);
    Storage::assertDirectoryEmpty('/');
});

it('refuses a non USD base currency before any import', function () {
    $channel = app(ChannelRepository::class)->findOneWhere(['code' => 'default']);
    $currency = app(CurrencyRepository::class)->findWhere([['code', '<>', 'USD']])->first();

    $currency ??= app(CurrencyRepository::class)->create(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal' => 2]);

    $channel->base_currency_id = $currency->id;
    $channel->save();
    $before = app(ProductRepository::class)->all()->count();
    $this->artisan('mx-theme:import-catalog', ['--no-interaction' => true])->assertFailed();
    expect(app(ProductRepository::class)->all()->count())->toBe($before);
});
