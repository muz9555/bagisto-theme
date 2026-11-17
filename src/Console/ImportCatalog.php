<?php

namespace Webkul\MXTechnology\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Webkul\Attribute\Repositories\AttributeFamilyRepository;
use Webkul\Attribute\Repositories\AttributeOptionRepository;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\CMS\Repositories\PageRepository;
use Webkul\Core\Repositories\ChannelRepository;
use Webkul\Product\Repositories\ProductAttributeValueRepository;
use Webkul\Product\Repositories\ProductImageRepository;
use Webkul\Product\Repositories\ProductRepository;

class ImportCatalog extends Command
{
    /**
     * Import reviewed manufacturer content without overwriting existing merchant records.
     */
    protected $signature = 'mx-theme:import-catalog {--channel=default : USD channel code} {--dry-run : Validate and preview without writes}';

    /**
     * Describe the catalog command.
     */
    protected $description = 'Add the sourced DJI, Beatbot and Roborock catalog at regular USD prices with zero stock';

    /**
     * Validate the complete package before creating any catalog records.
     */
    public function handle(ChannelRepository $channels, ProductRepository $products): int
    {
        $manifest = json_decode(file_get_contents(__DIR__.'/../Resources/catalog/products.json'), true, 512, JSON_THROW_ON_ERROR);
        $channel = $channels->findOneWhere(['code' => $this->option('channel')]);

        if (! $channel || $channel->base_currency->code !== 'USD') {
            $this->error('This reviewed price list requires a channel with USD as its base currency. No changes made.');

            return self::FAILURE;
        }

        if (! $channel->inventory_sources->count() || core()->getConfigData('catalog.inventory.stock_options.back_orders', $channel->code)) {
            $this->error('Assign an inventory source and disable backorders before importing zero-stock products. No changes made.');

            return self::FAILURE;
        }

        foreach ($manifest['products'] as $item) {
            if (! is_file(__DIR__.'/../Resources/public/catalog/'.$item['image']) || $item['price'] <= 0 || $item['currency'] !== 'USD') {
                $this->error('Invalid price or missing local image: '.$item['sku']);

                return self::FAILURE;
            }

            $existing = $products->findOneWhere(['sku' => $item['sku']]);
            $collision = $products->findByAttributeCode('url_key', $item['slug']);

            if ($collision && (! $existing || $collision->id !== $existing->id)) {
                $this->error('A product already uses URL '.$item['slug'].'. No changes made.');

                return self::FAILURE;
            }
        }

        $this->table(['SKU', 'Regular USD', 'Action'], array_map(fn ($item) => [
            $item['sku'], number_format($item['price'], 2), $products->findOneWhere(['sku' => $item['sku']]) ? 'Preserve existing' : 'Create, stock 0',
        ], $manifest['products']));

        if ($this->option('dry-run')) {
            $this->info('Dry run passed. No records, files, prices or quantities changed.');

            return self::SUCCESS;
        }

        $created = DB::transaction(function () use ($manifest, $channel, $products) {
            $family = $this->family($manifest);
            $brands = $this->brands($manifest);
            $categories = $this->categories($manifest, $channel);
            $created = collect();

            foreach ($manifest['products'] as $item) {
                if ($products->findOneWhere(['sku' => $item['sku']])) {
                    continue;
                }

                $product = $products->create(['type' => 'simple', 'attribute_family_id' => $family->id, 'sku' => $item['sku']]);
                $data = array_merge($item['specs'], [
                    'channel' => $channel->code,
                    'locale' => $channel->default_locale->code,
                    'sku' => $item['sku'],
                    'name' => $item['name'],
                    'url_key' => $item['slug'],
                    'short_description' => '<p>'.e($item['summary']).'</p>',
                    'description' => $this->description($item, $manifest),
                    'meta_title' => $item['name'],
                    'meta_description' => $item['summary'],
                    'price' => $item['price'],
                    'brand' => $brands[$item['brand']],
                    'status' => 1,
                    'visible_individually' => 1,
                    'featured' => 1,
                    'new' => 0,
                    'guest_checkout' => 1,
                    'manage_stock' => 1,
                    'channels' => [$channel->id],
                    'categories' => $this->productCategories($item, $manifest, $categories),
                    'inventories' => $channel->inventory_sources->mapWithKeys(fn ($source) => [$source->id => 0])->all(),
                ]);
                $product = $products->update($data, $product->id);

                foreach ($channel->locales as $locale) {
                    if ($locale->code !== $data['locale']) {
                        app(ProductAttributeValueRepository::class)->saveValues(array_merge($data, ['locale' => $locale->code]), $product, $family->custom_attributes);
                    }
                }

                $path = 'products/'.$product->id.'/mx-'.$item['image'];
                Storage::put($path, file_get_contents(__DIR__.'/../Resources/public/catalog/'.$item['image']));
                $image = app(ProductImageRepository::class)->create(['type' => 'images', 'path' => $path, 'product_id' => $product->id, 'position' => 1]);

                foreach ($channel->locales as $locale) {
                    $image->translateOrNew($locale->code)->alt_text = $item['name'].' — manufacturer photograph';
                }

                $image->save();
                $created->push($product);
            }

            $this->pages($channel);

            return $created;
        });

        foreach ($created as $product) {
            Event::dispatch('catalog.product.update.after', $product);
        }

        $this->call('responsecache:clear');
        $this->info('Created '.$created->count().' products. Existing SKUs, prices, images and inventory were preserved. Imported stock is 0.');

        return self::SUCCESS;
    }

    /**
     * Clone the default family and add public, comparable manufacturer specifications.
     */
    protected function family(array $manifest)
    {
        $families = app(AttributeFamilyRepository::class);

        if ($existing = $families->findOneWhere(['code' => 'mx_technology'])) {
            return $existing;
        }

        $attributes = app(AttributeRepository::class);
        $codes = collect($manifest['products'])->flatMap(fn ($item) => array_keys($item['specs']))->unique();
        $custom = $codes->map(function ($code) use ($attributes) {
            $attribute = $attributes->findOneWhere(['code' => $code]) ?? $attributes->create([
                'code' => $code, 'admin_name' => ucwords(str_replace('_', ' ', $code)), 'type' => 'text',
                'is_required' => 0, 'is_unique' => 0, 'is_user_defined' => 1, 'is_visible_on_front' => 1,
                'is_comparable' => 1, 'value_per_locale' => 0, 'value_per_channel' => 0, 'position' => 100,
                'en' => ['name' => ucwords(str_replace('_', ' ', $code))],
            ]);

            return ['id' => $attribute->id];
        });
        $base = $families->findOneWhere(['code' => 'default']);
        $groups = $base->attribute_groups->map(fn ($group) => [
            'code' => $group->code, 'name' => $group->name, 'column' => $group->column,
            'position' => $group->position, 'is_user_defined' => $group->is_user_defined,
            'custom_attributes' => $group->custom_attributes->reject(fn ($attribute) => in_array($attribute->code, ['color', 'size'], true))->map(fn ($attribute) => ['id' => $attribute->id])->values()->all(),
        ])->all();
        $groups[] = ['code' => 'manufacturer_specs', 'name' => 'Manufacturer specifications', 'column' => 1, 'position' => 20, 'is_user_defined' => 1, 'custom_attributes' => $custom->all()];

        return $families->create(['code' => 'mx_technology', 'name' => 'MX Technology', 'attribute_groups' => $groups]);
    }

    /**
     * Add actual catalog brand options without changing existing labels.
     */
    protected function brands(array $manifest): array
    {
        $attribute = app(AttributeRepository::class)->findOneWhere(['code' => 'brand']);
        $options = app(AttributeOptionRepository::class);
        $result = [];

        foreach (collect($manifest['products'])->pluck('brand')->unique() as $label) {
            $option = $options->findOneWhere(['attribute_id' => $attribute->id, 'admin_name' => $label]) ?? $options->create([
                'attribute_id' => $attribute->id, 'admin_name' => $label, 'sort_order' => 10,
                'en' => ['label' => $label],
            ]);
            $result[$label] = $option->id;
        }

        return $result;
    }

    /**
     * Create a channel-rooted category tree and product-backed category images.
     */
    protected function categories(array $manifest, $channel): array
    {
        $repository = app(CategoryRepository::class);
        $result = [];

        foreach ($manifest['categories'] as $index => [$slug, $name, $parent, $description]) {
            $existing = $repository->findBySlug($slug);
            $parentId = $parent ? $result[$parent] : $channel->root_category_id;

            if ($existing && $existing->parent_id !== $parentId) {
                throw new \RuntimeException('Category slug already exists in another tree: '.$slug);
            }

            $category = $existing ?? $repository->create([
                'locale' => 'all', 'name' => $name, 'slug' => $slug, 'description' => '<p>'.e($description).'</p>',
                'status' => 1, 'position' => $index + 1, 'parent_id' => $parentId, 'display_mode' => 'products_and_description',
                'meta_title' => $name, 'meta_description' => $description,
            ]);
            $result[$slug] = $category->id;

            if (! $existing) {
                $filterIds = app(AttributeRepository::class)->findWhereIn('code', ['brand', 'price'])->pluck('id')->all();
                $category->filterableAttributes()->sync($filterIds);
                $sample = collect($manifest['products'])->firstWhere('category', $slug);

                if ($sample) {
                    $path = 'category/'.$category->id.'/mx-'.$sample['image'];
                    Storage::put($path, file_get_contents(__DIR__.'/../Resources/public/catalog/'.$sample['image']));
                    $category->logo_path = $path;
                    $category->save();
                }
            }
        }

        return $result;
    }

    /**
     * Attach products to their leaf and parent collections for native category browsing.
     */
    protected function productCategories(array $item, array $manifest, array $categories): array
    {
        $ids = [];
        $slug = $item['category'];
        $parents = collect($manifest['categories'])->mapWithKeys(fn ($category) => [$category[0] => $category[2]]);

        while ($slug) {
            $ids[] = $categories[$slug];
            $slug = $parents[$slug];
        }

        return $ids;
    }

    /**
     * Write concise product facts with traceable manufacturer references.
     */
    protected function description(array $item, array $manifest): string
    {
        $html = '<h2>'.e($item['name']).'</h2><p>'.e($item['summary']).'</p><ul>';

        foreach ($item['specs'] as $code => $value) {
            $html .= '<li><strong>'.e(ucwords(str_replace('_', ' ', $code))).':</strong> '.e($value).'</li>';
        }

        $html .= '</ul><p>'.e($item['note']).'</p><h3>Manufacturer information</h3><p>Product specifications and photographs are sourced from the manufacturer. Performance varies with conditions and configuration.</p><ul>';

        foreach ($item['sources'] as $index => $url) {
            $html .= '<li><a href="'.e($url).'" target="_blank" rel="noopener">Manufacturer reference '.($index + 1).'</a></li>';
        }

        return $html.'</ul>';
    }

    /**
     * Add authored brand introductions and buying guides while retaining existing CMS content.
     */
    protected function pages($channel): void
    {
        $repository = app(PageRepository::class);
        $pages = json_decode(file_get_contents(__DIR__.'/../Resources/catalog/pages.json'), true, 512, JSON_THROW_ON_ERROR);

        foreach ($pages as $page) {
            if ($repository->findByUrlKey($page['url_key'])) {
                continue;
            }

            $page['html_content'] = preg_replace_callback('/href="(\/[^"#]*)"/', fn ($match) => 'href="'.e(url(ltrim($match[1], '/'))).'"', $page['html_content']);
            $repository->create(array_merge($page, ['channels' => [$channel->id], 'layout' => 'one-column', 'meta_title' => $page['page_title'], 'meta_description' => $page['summary']]));
        }
    }
}
