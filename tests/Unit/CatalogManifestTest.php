<?php

// ============================================================================
// Distributable Catalog Integrity
// ============================================================================

it('ships a complete optional catalog with unique identifiers and local images', function () {
    $root = dirname(__DIR__, 2).'/src/Resources';
    $manifest = json_decode(file_get_contents($root.'/catalog/products.json'), true, 512, JSON_THROW_ON_ERROR);
    $slugs = array_column($manifest['categories'], 0);
    $seen = [];

    foreach ($manifest['categories'] as [$slug, $name, $parent]) {
        expect($name)->not->toBeEmpty();

        if ($parent) {
            expect($seen)->toContain($parent);
        }

        $seen[] = $slug;
    }

    expect(array_unique($slugs))->toHaveCount(count($slugs))
        ->and(array_unique(array_column($manifest['products'], 'sku')))->toHaveCount(count($manifest['products']))
        ->and(array_unique(array_column($manifest['products'], 'slug')))->toHaveCount(count($manifest['products']));

    foreach ($manifest['products'] as $product) {
        expect($slugs)->toContain($product['category'])
            ->and($product['currency'])->toBe('USD')
            ->and($product['price'])->toBeGreaterThan(0)
            ->and($product['sources'])->not->toBeEmpty()
            ->and($product['image_source'])->toStartWith('https://')
            ->and(is_file($root.'/public/catalog/'.$product['image']))->toBeTrue();
    }
});
