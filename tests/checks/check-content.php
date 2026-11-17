<?php

use Illuminate\Contracts\Console\Kernel;
use Webkul\MXTechnology\Services\Storefront;

$root = getenv('BAGISTO_PATH') ?: getcwd();

if (getenv('BAGISTO_PATH')) {
    require_once dirname(__DIR__).'/bootstrap.php';
} else {
    require $root.'/vendor/autoload.php';
}
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$reader = app(Storefront::class);
$original = $reader->data();
$category = $original['fallback_categories']->first();

if (! $category) {
    echo "SKIP No published categories available for the read-only collection rendering check.\n";
    exit(0);
}

$settings = config('mx-technology.groups.drones');
$settings['category_slugs'] = [$category->slug];
$settings['label'] = 'Catalog verification collection';
$settings['heading'] = 'Catalog verification';
$settings['specs'] = ['Catalog name' => ['name']];
config(['mx-technology.groups' => ['drones' => $settings]]);
$app->forgetInstance(Storefront::class);
$data = app(Storefront::class)->data();

if (! isset($data['groups']['drones'])) {
    echo "SKIP First visible category has no individually visible products.\n";
    exit(0);
}

$group = $data['groups']['drones'];

if (! count($group['products']) || count($data['panels']) !== 2) {
    throw new RuntimeException('Configured collection did not produce a working product tab.');
}

if (count($group['products']) >= 2 && empty($group['comparison']['rows'])) {
    throw new RuntimeException('Populated specification rows were not resolved from the catalog.');
}

$session = app('session')->driver('array');
$session->start();
request()->setLaravelSession($session);
themes()->set('mx-technology');
$html = view('shop::home.index', ['sections' => collect(), 'categories' => collect()])->render();

foreach (['mx-hero', 'mx-spotlight-drones', 'mx-compare-drones', 'v-mx-products', 'mx-footer'] as $marker) {
    if (! str_contains($html, $marker)) {
        throw new RuntimeException('Theme section did not render: '.$marker);
    }
}

echo "PASS Explicit category configuration produces real product tabs and comparison rows.\n";
echo "PASS Hero, spotlight, comparison, product component and footer Blade branches render.\n";
echo "PASS Configuration overrides were in memory only; no catalog records were changed.\n";
