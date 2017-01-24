<?php

use \Soho\Packages\PkgContext as PkgContext;

return array(
    "file:handler.*.php",
    function (PkgContext $context, $packagename, $filepath, $item) {
        echo "Handler=$packagename File=$item\n";
        $handler = include $filepath;
        $context->addHandler($handler[0], $handler[1]);
    }
);