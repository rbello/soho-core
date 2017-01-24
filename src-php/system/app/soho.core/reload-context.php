<?php

include 'src-php/system/app/soho.core/config.php';

include BASE . 'system/app/soho.packages/pkg.context.php';

$ctx = new \Soho\Packages\PkgContext();

// Open packages directory
$handle = opendir($directory = BASE . 'system/packages');
if (!$handle) {
    throw new \Exception("Packages directory is not readable: {$directory}");
}

// Add handler for handlers
$handler = include BASE . 'system/app/soho.packages/pkg.handler.php';
$ctx->addHandler($handler[0], $handler[1]);

// Make a first turn to seek handlers
while (false !== ($entry = readdir($handle))) {
    if ($entry == '.' || $entry == '..') continue;
    $f = "$directory/$entry";
    if (!is_dir($f)) continue;
    $ctx->loadInstalledPackage($f);
}

// Back to beginning
rewinddir($handle);

$ctx->removeHandler($handler[0]);

// Make a second turn to load packages
while (false !== ($entry = readdir($handle))) {
    if ($entry == '.' || $entry == '..') continue;
    $f = "$directory/$entry";
    if (!is_dir($f)) continue;
    $ctx->loadInstalledPackage($f);
}

print_r($ctx->getContents());