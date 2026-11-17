<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Webkul\MXTechnology\Services\Storefront;
use Webkul\NowPayments\Payment\NowPayments;

$root = getenv('BAGISTO_PATH') ?: getcwd();

if (getenv('BAGISTO_PATH')) {
    require_once dirname(__DIR__).'/bootstrap.php';
} else {
    require $root.'/vendor/autoload.php';
}
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$data = app(Storefront::class)->data();
$assert = function (bool $condition, string $description): void {
    if (! $condition) {
        throw new RuntimeException($description);
    }
    echo 'PASS '.$description.PHP_EOL;
};
$assert(config('themes.shop.mx-technology.parent') === 'default', 'Default Shop theme remains the parent');
$assert(config('themes.shop.mx-technology.vite') === config('themes.shop.default.vite'), 'Original Shop assets and Vue application are reused');
$assert(file_exists(public_path('themes/shop/mx-technology/theme.css')), 'Theme stylesheet is published');
$assert(file_exists(public_path('themes/shop/mx-technology/theme.js')), 'Theme interactions are published');
$assert($data['store_name'] !== 'Elvix', 'Reference brand is not copied');
foreach ($data['groups'] as $group) {
    $assert(count($group['products']) > 0, 'Collection '.$group['label'].' has real channel products');
    foreach ($group['products'] as $product) {
        $assert(is_numeric($product['id']) && filled($product['url_key']) && filled($product['price_html']), 'Collection product has original identity and pricing');
    }
}
foreach (['shop.home.index', 'shop.search.index', 'shop.checkout.cart.index', 'shop.checkout.onepage.index', 'shop.customers.account.index', 'shop.home.contact_us'] as $route) {
    $assert(Route::has($route), 'Existing route '.$route.' remains registered');
}
echo 'Visible technology groups: '.count($data['groups']).PHP_EOL;
echo 'Published products in All: '.count($data['panels'][0]['products']).PHP_EOL;
echo 'No product, price, policy, order or payment record was modified by these checks.'.PHP_EOL;

if (class_exists(NowPayments::class)) {
    $assert(Route::has('nowpayments.redirect'), 'Installed NOWPayments gateway remains registered');
}
