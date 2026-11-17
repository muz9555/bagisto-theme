<?php

namespace Webkul\MXTechnology\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\CMS\Repositories\PageRepository;
use Webkul\Product\Enums\SearchContextEnum;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Product\Repositories\ProductReviewRepository;
use Webkul\Shop\Http\Resources\ProductCardResource;

class Storefront
{
    /**
     * The content snapshot shared by the current request's theme views.
     */
    protected ?array $snapshot = null;

    /**
     * The attribute definitions used to resolve factual specification labels.
     */
    protected array $attributes = [];

    /**
     * Create a catalog reader using Bagisto repositories.
     */
    public function __construct(
        protected CategoryRepository $categories,
        protected ProductRepository $products,
        protected AttributeRepository $attributeRepository,
        protected PageRepository $pages,
        protected ProductReviewRepository $reviews,
    ) {}

    /**
     * Build sections exclusively from published channel catalog data and merchant configuration.
     */
    public function data(): array
    {
        if ($this->snapshot !== null) {
            return $this->snapshot;
        }

        $channel = core()->getCurrentChannel();
        $categories = $this->flatten($this->categories->getVisibleCategoryTree($channel->root_category_id));
        $groups = [];
        $targetProducts = collect();
        $categoryCards = collect();

        foreach (config('mx-technology.groups', []) as $key => $settings) {
            $matched = $categories->filter(function ($category) use ($settings) {
                $slugs = $settings['category_slugs'] ?? [];

                if ($slugs) {
                    return in_array($category->slug, $slugs, true);
                }

                $name = Str::lower($category->name.' '.$category->slug);

                return collect($settings['matches'] ?? [])->contains(fn ($word) => Str::contains($name, $word));
            });

            if ($matched->isEmpty()) {
                continue;
            }

            $members = $categories->filter(fn ($category) => $matched->contains(
                fn ($parent) => $category->_lft >= $parent->_lft && $category->_rgt <= $parent->_rgt
            ));
            $ids = $members->pluck('id')->all();
            $accessoryMembers = $members->filter(fn ($category) => Str::contains(Str::lower($category->name.' '.$category->slug), ['accessor', 'spare', 'attachment']));
            $items = $this->listing(['category_id' => implode(',', $ids), 'limit' => 24])->reject(
                fn ($product) => $product->categories->whereIn('id', $accessoryMembers->pluck('id'))->isNotEmpty()
            )->take(8);

            if ($items->isEmpty()) {
                continue;
            }

            $first = $matched->first();
            $cards = $items->map(fn ($product) => $this->card($product));
            $brands = $this->brands($items);
            $image = $this->contentUrl($settings['image'] ?? null) ?: ($first->banner_url ?: product_image()->getProductBaseImage($items->first())['large_image_url']);
            $brandName = $settings['brand'] ?? null;
            $hasBrand = $brandName && $brands->contains(fn ($brand) => Str::lower($brand['label']) === Str::lower($brandName));
            $groups[$key] = array_merge($settings, [
                'key' => $key,
                'category_ids' => $ids,
                'url' => route('shop.search.index', ['category_id' => implode(',', $ids)]),
                'image' => $image,
                'image_is_product' => $settings['image_is_product'] ?? (blank($settings['image']) && blank($first->banner_url)),
                'products' => $cards->values()->all(),
                'models' => $items,
                'brands' => $brands->values()->all(),
                'button' => $hasBrand ? $settings['button'] : 'Explore '.$settings['label'],
                'intro' => $hasBrand || ! $brandName ? $settings['intro'] : 'Explore the collection and compare the details that matter to you.',
                'comparison' => $this->comparison($items->take(3), $settings['specs'] ?? []),
            ]);
            $targetProducts = $targetProducts->merge($items)->unique('id');
            $categoryCards = $categoryCards->merge($members->filter(
                fn ($category) => $this->listing(['category_id' => (string) $category->id, 'limit' => 1])->isNotEmpty()
            ));
        }

        $allProducts = $targetProducts->isNotEmpty() ? $targetProducts->take(9) : $this->listing(['limit' => 8]);
        $panels = [['key' => 'all', 'label' => 'All', 'products' => $allProducts->map(fn ($p) => $this->card($p))->values()->all()]];

        foreach ($groups as $group) {
            $panels[] = ['key' => $group['key'], 'label' => $group['label'], 'products' => $group['products']];
        }

        $pages = $this->pages->all()->filter(fn ($page) => $page->channels->contains('id', $channel->id));
        $policyLinks = $pages->filter(fn ($page) => Str::contains($page->url_key, ['shipping', 'return', 'refund', 'privacy', 'terms', 'payment', 'customer-service']));
        $benefits = $policyLinks->filter(fn ($page) => Str::contains($page->url_key, ['shipping', 'return', 'payment', 'customer-service']))->take(4);
        $reviewIds = $targetProducts->pluck('id')->all();
        $approved = $reviewIds ? $this->reviews->findWhere([['product_id', 'in', $reviewIds], ['status', '=', 'approved']])->take(3) : collect();
        $accessories = $categoryCards->filter(fn ($cat) => Str::contains(Str::lower($cat->name.' '.$cat->slug), ['accessor', 'spare', 'attachment']));

        return $this->snapshot = [
            'store_name' => $channel->name !== 'Default' ? $channel->name : config('app.name'),
            'brand_details' => collect(config('mx-technology.brand_details', []))->filter(fn ($brand) => isset($groups[$brand['group'] ?? '']) && $this->linkAvailable($brand['url'] ?? '', $pages))->map(fn ($brand) => array_replace($brand, ['url' => $this->contentUrl($brand['url'])])),
            'accessory_url' => $accessories->isNotEmpty() ? route('shop.search.index', ['category_id' => $accessories->pluck('id')->implode(',')]) : route('shop.search.index'),
            'accessory_panels' => [['key' => 'accessories', 'label' => 'Accessories', 'products' => $accessories->isNotEmpty() ? $this->listing(['category_id' => $accessories->pluck('id')->implode(','), 'limit' => 6])->map(fn ($product) => $this->card($product))->values()->all() : []]],
            'logo' => $channel->logo_url,
            'accent' => preg_match('/^#[0-9a-f]{6}$/iD', config('mx-technology.accent', '')) ? config('mx-technology.accent') : '#2563eb',
            'announcement' => config('mx-technology.announcement'),
            'groups' => $groups,
            'panels' => $panels,
            'categories' => $categoryCards->unique('id')->filter(fn ($category) => $category->parent_id !== $channel->root_category_id)->take(8),
            'fallback_categories' => $categories->filter(fn ($cat) => $cat->parent_id === $channel->root_category_id)->take(6),
            'policies' => $policyLinks,
            'benefits' => $benefits,
            'brands' => $this->brands($targetProducts)->values(),
            'reviews' => $approved,
            'accessories' => $accessories->take(6),
            'videos' => collect(config('mx-technology.videos', []))->filter(fn ($video) => isset($groups[$video['group'] ?? '']) && filled($video['url'] ?? null) && filled($video['poster'] ?? null))->map(fn ($video) => array_replace($video, ['url' => $this->contentUrl($video['url']), 'poster' => $this->contentUrl($video['poster'])])),
            'demos' => collect(config('mx-technology.demos', []))->filter(fn ($demo) => isset($groups[$demo['group'] ?? '']) && filled($demo['url'] ?? null) && filled($demo['poster'] ?? null))->map(fn ($demo) => array_replace($demo, ['url' => $this->contentUrl($demo['url']), 'poster' => $this->contentUrl($demo['poster'])])),
            'guides' => collect(config('mx-technology.guides', []))->filter(fn ($guide) => isset($groups[$guide['group'] ?? '']) && $this->linkAvailable($guide['url'] ?? '', $pages) && filled($guide['title'] ?? null))->map(fn ($guide) => array_replace($guide, ['url' => $this->contentUrl($guide['url'])])),
        ];
    }

    /**
     * Resolve local content against the application root, including subdirectory installations.
     */
    protected function contentUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (preg_match('~^(?:[a-z][a-z0-9+.-]*:|//|#)~i', $path)) {
            return $path;
        }

        return url(ltrim($path, '/'));
    }

    /**
     * Omit default CMS links until their page exists in the active sales channel.
     */
    protected function linkAvailable(string $url, Collection $pages): bool
    {
        if (blank($url)) {
            return false;
        }

        $path = ltrim($url, '/');

        return ! Str::startsWith($path, 'page/') || $pages->contains('url_key', Str::after($path, 'page/'));
    }

    /**
     * Read only published products visible in the current channel.
     */
    protected function listing(array $filters): Collection
    {
        return collect($this->products->setSearchContext(SearchContextEnum::STOREFRONT)->getAll(array_merge($filters, [
            'channel_id' => core()->getCurrentChannel()->id,
            'status' => 1,
            'visible_individually' => 1,
        ]))->items())->take($filters['limit'] ?? 8);
    }

    /**
     * Flatten visible categories while retaining their channel root boundaries.
     */
    protected function flatten(iterable $categories): Collection
    {
        $result = collect();

        foreach ($categories as $category) {
            $result->push($category);
            $result = $result->merge($this->flatten($category->children));
        }

        return $result;
    }

    /**
     * Reuse Bagisto's native product payload and append catalog-only descriptive text.
     */
    protected function card($product): array
    {
        return array_merge((new ProductCardResource($product))->resolve(), [
            'mx_brand' => $this->attributeValue($product, 'brand'),
            'mx_benefit' => Str::limit(html_entity_decode(strip_tags($product->short_description ?? '')), 105),
        ]);
    }

    /**
     * Resolve select options and omit unspecified or nonpublic attribute values.
     */
    protected function attributeValue($product, string $code): ?string
    {
        if (! array_key_exists($code, $this->attributes)) {
            $this->attributes[$code] = $this->attributeRepository->findOneWhere(['code' => $code]);
        }

        $attribute = $this->attributes[$code];

        if (! $attribute || (! $attribute->is_visible_on_front && ! $attribute->is_comparable)) {
            return null;
        }

        $value = $product->{$code};

        if ($value === null || $value === '') {
            return null;
        }

        if (in_array($attribute->type, ['select', 'multiselect'], true)) {
            return $attribute->options->whereIn('id', explode(',', (string) $value))->map(fn ($option) => $option->label ?: $option->admin_name)->filter()->implode(', ') ?: null;
        }

        if ($attribute->type === 'boolean') {
            return $value ? 'Yes' : 'No';
        }

        return is_scalar($value) ? strip_tags((string) $value) : null;
    }

    /**
     * List brands actually present on products in the displayed category groups.
     */
    protected function brands(Collection $items): Collection
    {
        return $items->map(function ($product) {
            $label = $this->attributeValue($product, 'brand');

            return $label ? ['label' => $label, 'url' => route('shop.search.index', ['brand' => $product->brand])] : null;
        })->filter()->unique('label');
    }

    /**
     * Compare models within a category using catalog attributes and actual prices.
     */
    protected function comparison(Collection $products, array $specs): array
    {
        if ($products->count() < 2) {
            return [];
        }

        $rows = [];

        foreach ($specs as $label => $codes) {
            $values = $products->map(function ($product) use ($codes) {
                foreach ($codes as $code) {
                    if ($value = $this->attributeValue($product, $code)) {
                        return $value;
                    }
                }

                return null;
            });

            if ($values->filter()->isNotEmpty()) {
                $rows[] = ['label' => $label, 'values' => $values->all()];
            }
        }

        if (! $rows) {
            return [];
        }

        $rows[] = ['label' => 'Price', 'values' => $products->map(fn ($p) => core()->formatPrice($p->getTypeInstance()->getMinimalPrice()))->all()];

        return ['products' => $products->map(fn ($p) => ['name' => $p->name, 'url' => route('shop.product_or_category.index', $p->url_key)])->all(), 'rows' => $rows];
    }
}
