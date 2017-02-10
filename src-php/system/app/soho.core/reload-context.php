<?php

echo "Reload application context...\n";

include 'src-php/system/app/soho.core/config.php';

include BASE . 'system/app/soho.core/soho.php';

include BASE . 'system/app/soho.packages/pkg.context.php';

$ctx = new \Soho\Packages\PkgContext();

// Open packages directory
$handle = opendir($directory = BASE . 'system/packages');
if (!$handle) {
    throw new \Exception("Packages directory is not readable: {$directory}");
}

// Add handler for handlers
$handler1 = include BASE . 'system/app/soho.packages/handler.handler.php';
$ctx->addHandler($handler1[0], $handler1[1]);

// Add handler for routers
$handler2 = include BASE . 'system/app/soho.packages/handler.router.php';
$ctx->addHandler($handler2[0], $handler2[1]);

echo "  Seek handlers...\n";

// Make a first turn to seek handlers
while (false !== ($entry = readdir($handle))) {
    if ($entry == '.' || $entry == '..') continue;
    $f = "$directory/$entry";
    if (!is_dir($f)) continue;
    $ctx->loadInstalledPackage($f);
}

echo "  Load handlers...\n";

foreach ($handler1[2] as $hd) {
    $ctx->addHandler($hd[0], $hd[1]);
}

// Back to beginning
rewinddir($handle);

$ctx->removeHandler($handler1[0]);
$ctx->removeHandler($handler2[0]);

// Add handler for plugins
$handler3 = include BASE . 'system/app/soho.packages/handler.plugin.php';
$ctx->addHandler($handler3[0], $handler3[1]);

echo "  Load packages...\n";

// Make a second turn to load packages
while (false !== ($entry = readdir($handle))) {
    if ($entry == '.' || $entry == '..') continue;
    $f = "$directory/$entry";
    if (!is_dir($f)) continue;
    echo "   * Package: $entry\n";
    $ctx->loadInstalledPackage($f);
}

$f = BASE . 'data/cache/app/context.cache.php';
echo "  Write to: $f\n";
file_put_contents($f, '<?php return ' . var_export($ctx->getContents(), true) . ';');

//print_r($ctx->getContents());