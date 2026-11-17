<?php

$bagisto = getenv('BAGISTO_PATH');

if ($bagisto) {
    $autoload = rtrim($bagisto, '/').'/vendor/autoload.php';

    if (! is_file($autoload)) {
        throw new RuntimeException('BAGISTO_PATH must point to an installed Bagisto 2.5 application.');
    }

    $loader = require $autoload;
    spl_autoload_register(function (string $class): void {
        $prefix = 'Webkul\\MXTechnology\\';

        if (! str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $directory = str_starts_with($relative, 'Tests\\') ? __DIR__ : dirname(__DIR__).'/src';
        $relative = str_starts_with($relative, 'Tests\\') ? substr($relative, 6) : $relative;
        $file = $directory.'/'.str_replace('\\', '/', $relative).'.php';

        if (is_file($file)) {
            require $file;
        }
    }, true, true);
    putenv('APP_BASE_PATH='.rtrim($bagisto, '/'));
    $_ENV['APP_BASE_PATH'] = rtrim($bagisto, '/');
    $_SERVER['APP_BASE_PATH'] = rtrim($bagisto, '/');
} else {
    require dirname(__DIR__).'/vendor/autoload.php';
}
