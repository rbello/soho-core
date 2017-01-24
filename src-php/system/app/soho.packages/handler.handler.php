<?php

use \Soho\Packages\PkgContext as PkgContext;

$handlers = array();

return array(
    "file:handler.*.php",
    function (PkgContext $context, $packagename, $filepath, $item) use (&$handlers) {
        echo "\t* New Handler $item (package $packagename)\n";
        $handler = include $filepath;
        $handlers[] = $handler;
    },
    &$handlers
);