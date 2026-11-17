@php
    $mx = $mx ?? app(\Webkul\MXTechnology\Services\Storefront::class)->data();
    $heroGroups = array_values($mx['groups']);
    $primary = $heroGroups[0] ?? null;
    $comparable = array_filter($mx['groups'], fn ($group) => ! empty($group['comparison']));
@endphp
@push('meta')
    <meta name="description" content="{{ (core()->getCurrentChannel()->home_seo['meta_description'] ?? '') ?: config('mx-technology.hero.intro') }}">
@endpush
<x-shop::layouts :has-feature="false">
    <x-slot:title>{{ (core()->getCurrentChannel()->home_seo['meta_title'] ?? '') ?: $mx['store_name'] }}</x-slot>
    <div class="mx-home" style="--mx-accent: {{ $mx['accent'] }}">
        <section class="mx-hero mx-wrap" aria-labelledby="mx-hero-title">
            <div class="mx-hero-lead {{ $primary ? '' : 'mx-hero-no-photo' }}">
                @if ($primary)<img class="mx-hero-photo {{ $primary['image_is_product'] ? 'mx-product-photo' : '' }}" src="{{ $primary['image'] }}" alt="{{ $primary['label'] }} collection" width="1000" height="760" fetchpriority="high">@endif
                <div class="mx-hero-copy"><p class="mx-kicker">Make room for possibility</p><h1 id="mx-hero-title">{!! nl2br(e(str_replace('. ', ".\n", config('mx-technology.hero.headline')))) !!}</h1><p>{{ config('mx-technology.hero.intro') }}</p><a class="mx-button" href="{{ $primary['url'] ?? route('shop.search.index') }}">{{ $primary ? 'Shop '.$primary['label'] : 'Explore the collection' }} @include('mx-technology::partials.icon', ['name' => 'arrow'])</a></div>
                <span class="mx-hero-index" aria-hidden="true">01 / EXPLORE</span>
            </div>
            <div class="mx-hero-side">
                @for ($i = 1; $i <= 2; $i++)
                    @php($tile = $heroGroups[$i] ?? null)
                    <a class="mx-hero-tile mx-hero-tile-{{ $i }}" href="{{ $tile['url'] ?? ($i === 1 ? route('shop.search.index') : route('shop.home.contact_us')) }}">
                        @if ($tile)<img src="{{ $tile['image'] }}" alt="{{ $tile['label'] }} collection" width="500" height="400" class="{{ $tile['image_is_product'] ? 'mx-product-photo' : '' }}">@endif
                        <div><p class="mx-kicker">{{ $tile['label'] ?? ($i === 1 ? 'Find your next favorite' : 'A little guidance') }}</p><h2>{{ $tile['heading'] ?? ($i === 1 ? 'Made for your next move.' : 'Good advice. Right choice.') }}</h2><span class="mx-text-link">{{ $tile ? 'Shop '.$tile['label'] : ($i === 1 ? 'Discover the collection' : 'Talk to us') }} <span aria-hidden="true">↗</span></span></div>
                    </a>
                @endfor
            </div>
        </section>

        @if ($mx['benefits']->isNotEmpty())
            <section class="mx-wrap mx-benefits" aria-label="Store information">
                @foreach ($mx['benefits'] as $page)<a href="{{ route('shop.cms.page', $page->url_key) }}">@include('mx-technology::partials.icon', ['name' => 'box'])<div><h2>{{ $page->page_title }}</h2><span>Read the details <span aria-hidden="true">↗</span></span></div></a>@endforeach
            </section>
        @endif

        @if ($mx['categories']->isNotEmpty())
            <section class="mx-section mx-wrap" aria-labelledby="mx-category-title"><div class="mx-section-head"><div><p class="mx-kicker">Your world, upgraded</p><h2 id="mx-category-title">Find your kind of extraordinary.</h2></div><a href="{{ route('shop.search.index') }}" class="mx-text-link">Explore all <span aria-hidden="true">↗</span></a></div>
                <div class="mx-category-grid">@foreach ($mx['categories'] as $category)<a href="{{ $category->url }}" class="mx-category-card">@if ($category->logo_url)<img src="{{ $category->logo_url }}" alt="{{ $category->logo_alt ?: $category->name }}" loading="lazy" width="360" height="300">@endif<div><h3>{{ $category->name }}</h3><span aria-hidden="true">↗</span></div></a>@endforeach</div>
            </section>
        @endif

        @if (count($mx['panels'][0]['products']))
            <section class="mx-section mx-soft" aria-labelledby="mx-featured-title"><div class="mx-wrap"><div class="mx-section-head"><div><p class="mx-kicker">Discover the collection</p><h2 id="mx-featured-title">Drones and robots. Chosen for your world.</h2></div><a class="mx-text-link" href="{{ route('shop.search.index') }}">Shop all products <span aria-hidden="true">↗</span></a></div>
                <v-mx-products :panels='@json($mx["panels"])'></v-mx-products>
            </div></section>
        @endif

        @foreach ($mx['groups'] as $group)
            @php($spotlightPanels = [['key' => $group['key'], 'label' => $group['label'], 'products' => array_slice($group['products'], 0, 4)]])
            <section class="mx-section mx-wrap" aria-labelledby="mx-spotlight-{{ $group['key'] }}"><div class="mx-spotlight mx-spotlight-{{ $group['key'] }}">
                <img src="{{ $group['image'] }}" alt="{{ $group['label'] }}" width="1400" height="660" loading="lazy" class="{{ $group['image_is_product'] ? 'mx-product-photo' : '' }}">
                <div><p class="mx-kicker">{{ $group['label'] }}</p><h2 id="mx-spotlight-{{ $group['key'] }}">{{ $group['heading'] }}</h2><p>{{ $group['intro'] }}</p><a href="{{ $group['url'] }}" class="mx-button">{{ $group['button'] }} @include('mx-technology::partials.icon', ['name' => 'arrow'])</a></div>
            </div><v-mx-products :panels='@json($spotlightPanels)' :compact="true"></v-mx-products></section>
        @endforeach

        @if ($mx['groups'])
            <section class="mx-section mx-dark" aria-labelledby="mx-finder-title"><div class="mx-wrap"><p class="mx-kicker">Made for the way you live</p><h2 id="mx-finder-title">Find your perfect match.</h2><div class="mx-finder-grid">@foreach ($mx['groups'] as $group)<a href="{{ $group['url'] }}"><span class="mx-kicker">{{ $loop->iteration < 10 ? '0'.$loop->iteration : $loop->iteration }}</span><h3>Find your {{ ['drones' => 'drone', 'pool' => 'pool robot', 'vacuums' => 'vacuum cleaner'][$group['key']] ?? $group['label'] }}.</h3><p>Explore the collection and see the details.</p><span class="mx-circle-arrow" aria-hidden="true">↗</span></a>@endforeach</div></div></section>
        @endif

        @if ($comparable)
            <section class="mx-section mx-wrap" aria-labelledby="mx-compare-title"><div class="mx-section-head"><div><p class="mx-kicker">The details make the difference</p><h2 id="mx-compare-title">A clearer comparison.</h2></div></div><div data-mx-tabs><div class="mx-tabs" role="tablist" aria-label="Comparison category">@foreach ($comparable as $group)<button id="mx-compare-tab-{{ $group['key'] }}" role="tab" type="button" data-mx-target="mx-compare-{{ $group['key'] }}" aria-controls="mx-compare-{{ $group['key'] }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? 0 : -1 }}">{{ $group['label'] }}</button>@endforeach</div>
                @foreach ($comparable as $group)<div class="mx-comparison-scroll" role="tabpanel" tabindex="0" aria-labelledby="mx-compare-tab-{{ $group['key'] }}" id="mx-compare-{{ $group['key'] }}" @if (! $loop->first) hidden @endif><table><caption class="mx-sr">{{ $group['label'] }} model comparison</caption><thead><tr><th scope="col">Compare the details</th>@foreach ($group['comparison']['products'] as $product)<th scope="col"><a href="{{ $product['url'] }}">{{ $product['name'] }} <span aria-hidden="true">↗</span></a></th>@endforeach</tr></thead><tbody>@foreach ($group['comparison']['rows'] as $row)<tr><th scope="row">{{ $row['label'] }}</th>@foreach ($row['values'] as $value)<td>{{ $value ?? '—' }}</td>@endforeach</tr>@endforeach</tbody></table></div>@endforeach
            </div></section>
        @endif

        @if ($mx['accessories']->isNotEmpty())
            <section class="mx-section mx-soft"><div class="mx-wrap"><p class="mx-kicker">The finishing touches</p><h2>Little extras. More possibilities.</h2><v-mx-products :panels='@json($mx["accessory_panels"])' :compact="true"></v-mx-products><div class="mx-category-grid">@foreach ($mx['accessories'] as $category)<a class="mx-category-card" href="{{ $category->url }}">@if ($category->logo_url)<img src="{{ $category->logo_url }}" alt="{{ $category->name }}" loading="lazy" width="360" height="300">@endif<div><h3>{{ $category->name }}</h3><span aria-hidden="true">↗</span></div></a>@endforeach</div></div></section>
        @endif

        @if ($mx['demos']->isNotEmpty())
            <section class="mx-section mx-wrap" aria-labelledby="mx-demo-title"><p class="mx-kicker">From the manufacturers</p><h2 id="mx-demo-title">See the technology in action.</h2><div class="mx-demo-grid">@foreach ($mx['demos'] as $demo)@if (isset($mx['groups'][$demo['group']]))<a href="{{ $demo['url'] }}" target="_blank" rel="noopener noreferrer"><img src="{{ $demo['poster'] }}" alt="{{ $demo['title'] }}" width="600" height="400" loading="lazy"><div><h3>{{ $demo['title'] }}</h3><span>Explore official demos ↗</span></div></a>@endif
@endforeach</div></section>
        @endif

        @if ($mx['videos']->isNotEmpty())
            <section class="mx-section mx-wrap"><p class="mx-kicker">A closer look</p><h2>See them in action.</h2><div class="mx-video-grid">@foreach ($mx['videos'] as $video)<figure><video controls preload="none" poster="{{ $video['poster'] }}" aria-label="{{ $video['title'] }}"><source src="{{ $video['url'] }}">Your browser does not support embedded video.</video><figcaption>{{ $video['title'] }}</figcaption></figure>@endforeach</div></section>
        @endif

        @if ($mx['reviews']->isNotEmpty())
            <section class="mx-section mx-soft"><div class="mx-wrap"><p class="mx-kicker">From the people who use them</p><h2>Real experiences. In their words.</h2><div class="mx-review-grid">@foreach ($mx['reviews'] as $review)<figure><p aria-label="{{ $review->rating }} out of 5 stars">{{ str_repeat('★', max(0, min(5, (int) $review->rating))) }}</p><blockquote>{{ \Illuminate\Support\Str::limit(strip_tags($review->comment), 280) }}</blockquote><figcaption>{{ $review->name }}</figcaption></figure>@endforeach</div></div></section>
        @endif

        @if ($mx['guides']->isNotEmpty() || $mx['policies']->isNotEmpty())
            <section class="mx-section mx-wrap mx-guide-section"><div><p class="mx-kicker">A little know-how goes a long way</p><h2>Good questions.<br>Clear answers.</h2>@foreach ($mx['guides'] as $guide)<a class="mx-guide-link" href="{{ $guide['url'] }}">{{ $guide['title'] }} <span aria-hidden="true">↗</span></a>@endforeach</div><div class="mx-faqs">@foreach ($mx['policies']->filter(fn ($page) => \Illuminate\Support\Str::contains($page->url_key, ['shipping', 'return', 'payment', 'customer-service']))->take(4) as $page)<details><summary>{{ $page->page_title }} @include('mx-technology::partials.icon', ['name' => 'plus'])</summary><p>Find the current information in our <a href="{{ route('shop.cms.page', $page->url_key) }}">{{ \Illuminate\Support\Str::lower($page->page_title) }}</a>, or <a href="{{ route('shop.home.contact_us') }}">contact the team</a> for help.</p></details>@endforeach</div></section>
        @endif

        @if ($mx['brand_details']->isNotEmpty())
            <section class="mx-section mx-wrap" aria-labelledby="mx-brand-title"><p class="mx-kicker">Get to know the brands</p><h2 id="mx-brand-title">Three specialists. More possibilities.</h2><div class="mx-brand-grid">@foreach ($mx['brand_details'] as $brand)<article><p class="mx-kicker">{{ $brand['focus'] }}</p><h3>{{ $brand['name'] }}</h3><p>{{ $brand['intro'] }}</p><a class="mx-text-link" href="{{ $brand['url'] }}">Discover {{ $brand['name'] }} ↗</a></article>@endforeach</div></section>
        @endif
        @if ($mx['brands']->isNotEmpty())<section id="mx-brands" class="mx-wrap mx-brand-strip" aria-label="Shop by brand"><p class="mx-kicker">Explore by brand</p><div>@foreach ($mx['brands'] as $brand)<a href="{{ $brand['url'] }}">{{ $brand['label'] }}</a>@endforeach</div></section>@endif
        <section class="mx-wrap mx-advice"><div><p class="mx-kicker">A good choice starts with a conversation</p><h2>Need help choosing?</h2></div><a class="mx-button mx-button-light" href="{{ route('shop.home.contact_us') }}">Let's find your fit @include('mx-technology::partials.icon', ['name' => 'arrow'])</a></section>
    </div>
    @pushOnce('scripts')
        @include('mx-technology::partials.products')
    @endPushOnce
    @if ($preview ?? false)@include('shop::home.preview-bridge')@endif
</x-shop::layouts>
