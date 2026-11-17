@php
    $mx = $mx ?? app(\Webkul\MXTechnology\Services\Storefront::class)->data();
@endphp
{!! view_render_event('bagisto.shop.layout.footer.before') !!}
<footer class="mx-footer">
    <div class="mx-wrap">
        <div class="mx-footer-top">
            <div><p class="mx-kicker">{{ $mx['store_name'] }}</p><h2>A little more possibility.<br>A little less everyday.</h2></div>
            @if (core()->getConfigData('customer.settings.newsletter.subscription'))
                <form method="post" action="{{ route('shop.subscription.store') }}" class="mx-newsletter">
                    @csrf
                    <label for="mx-newsletter">Stay in the loop.</label>
                    <div><input id="mx-newsletter" type="email" name="email" placeholder="Your email address" required autocomplete="email" maxlength="255"><button type="submit" aria-label="Subscribe to newsletter">@include('mx-technology::partials.icon', ['name' => 'arrow'])</button></div>
                </form>
            @endif
        </div>
        <div class="mx-footer-columns">
            <div><a class="mx-brand" href="{{ route('shop.home.index') }}">{{ $mx['store_name'] }}<span class="mx-brand-dot" aria-hidden="true"></span></a><p>Explore more.<br>Clean smarter.</p></div>
            <nav aria-label="Footer shopping"><h3>Explore</h3>
                @forelse ($mx['groups'] as $group)<a href="{{ $group['url'] }}">{{ $group['label'] }}</a>@empty<a href="{{ route('shop.search.index') }}">Shop all products</a>@endforelse
                @if ($mx['accessories']->isNotEmpty())<a href="{{ $mx['accessory_url'] }}">Accessories</a>@endif
                @foreach ($mx['brands'] as $brand)<a href="{{ $brand['url'] }}">{{ $brand['label'] }}</a>@endforeach
            </nav>
            <nav aria-label="Customer support"><h3>Here to help</h3><a href="{{ route('shop.home.contact_us') }}">Contact & product advice</a><a href="{{ route('shop.customers.account.index') }}">Your account</a>
                @foreach (config('mx-technology.contact_links', []) as $link)<a href="{{ $link['url'] }}">{{ $link['label'] }}</a>@endforeach
            </nav>
            <nav aria-label="Store policies"><h3>Good to know</h3>@foreach ($mx['policies']->take(6) as $page)<a href="{{ route('shop.cms.page', $page->url_key) }}">{{ $page->page_title }}</a>@endforeach</nav>
        </div>
        <div class="mx-footer-bottom"><span>© {{ date('Y') }} {{ $mx['store_name'] }}.</span><div>@foreach (config('mx-technology.social_links', []) as $link)<a href="{{ $link['url'] }}" rel="noopener noreferrer">{{ $link['label'] }}</a>@endforeach<span>Explore. Discover. Make it yours.</span></div></div>
    </div>
</footer>
{!! view_render_event('bagisto.shop.layout.footer.after') !!}
