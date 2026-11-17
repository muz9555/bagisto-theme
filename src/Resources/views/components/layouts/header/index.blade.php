@php
    $mx = $mx ?? app(\Webkul\MXTechnology\Services\Storefront::class)->data();
@endphp
{!! view_render_event('bagisto.shop.layout.header.before') !!}
@if ($mx['announcement'])
    <div class="mx-announcement">{{ $mx['announcement'] }}</div>
@endif
<header class="mx-header" style="--mx-accent: {{ $mx['accent'] }}">
    <div class="mx-wrap mx-header-main">
        <a class="mx-brand" href="{{ route('shop.home.index') }}" aria-label="{{ $mx['store_name'] }} home">
            @if ($mx['logo'])
                <img src="{{ $mx['logo'] }}" alt="{{ core()->getCurrentChannel()->logo_alt ?: $mx['store_name'] }}" width="150" height="40">
            @else
                <span>{{ $mx['store_name'] }}</span><span class="mx-brand-dot" aria-hidden="true"></span>
            @endif
        </a>
        <form class="mx-search" action="{{ route('shop.search.index') }}" role="search">
            <label class="mx-sr" for="mx-search">Search products</label>
            <input id="mx-search" name="query" type="search" placeholder="Search the collection…" value="{{ is_string(request('query')) ? request('query') : '' }}" maxlength="1000" required>
            <button type="submit" aria-label="Search">@include('mx-technology::partials.icon', ['name' => 'search'])</button>
        </form>
        <div class="mx-header-actions">
            <a href="{{ auth()->guard('customer')->check() ? route('shop.customers.account.index') : route('shop.customer.session.index') }}" aria-label="My account">@include('mx-technology::partials.icon', ['name' => 'account'])</a>
            @if (core()->getConfigData('catalog.products.settings.wishlist_option'))
                <a href="{{ route('shop.customers.account.wishlist.index') }}" aria-label="Wishlist">@include('mx-technology::partials.icon', ['name' => 'heart'])</a>
            @endif
            <div class="mx-native-cart">@include('shop::checkout.cart.mini-cart')</div>
        </div>
    </div>
    <div class="mx-header-rule">
        <div class="mx-wrap mx-navigation">
            <details class="mx-mobile-menu">
                <summary>@include('mx-technology::partials.icon', ['name' => 'menu']) <span>Menu</span></summary>
                <nav aria-label="Mobile navigation">
                    @foreach ($mx['groups'] as $group)<a href="{{ $group['url'] }}">{{ $group['label'] }}</a>@endforeach
                    <a href="{{ route('shop.search.index') }}">Shop all products</a>
                    @if ($mx['accessories']->isNotEmpty())<a href="{{ $mx['accessory_url'] }}">Accessories</a>@endif
                    @if ($mx['brands']->isNotEmpty())<a href="{{ route('shop.home.index') }}#mx-brands">Shop by Brand</a>@endif
                    @if (core()->getConfigData('catalog.products.settings.compare_option'))<a href="{{ route('shop.compare.index') }}">Compare</a>@endif
                    <a href="{{ route('shop.home.contact_us') }}">Support</a>
                </nav>
            </details>
            <nav class="mx-desktop-nav" aria-label="Main navigation">
                @foreach ($mx['groups'] as $group)<a href="{{ $group['url'] }}">{{ $group['label'] }}</a>@endforeach
                @if (! $mx['groups'])<a href="{{ route('shop.search.index') }}">Shop the collection</a>@endif
                @if ($mx['accessories']->isNotEmpty())<a href="{{ $mx['accessory_url'] }}">Accessories</a>@endif
                @if ($mx['brands']->isNotEmpty())<a href="{{ route('shop.home.index') }}#mx-brands">Shop by Brand</a>@endif
                @if (core()->getConfigData('catalog.products.settings.compare_option'))<a href="{{ route('shop.compare.index') }}">Compare</a>@endif
            </nav>
            <a class="mx-support-link" href="{{ route('shop.home.contact_us') }}">Support <span aria-hidden="true">↗</span></a>
        </div>
    </div>
</header>
{!! view_render_event('bagisto.shop.layout.header.after') !!}
