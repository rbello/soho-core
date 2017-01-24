<?php

use \Soho\Packages\PkgContext as PkgContext;

return array(
    "file:router.*.php",
    function (PkgContext $context, $packagename, $filepath, $item) {
        echo "\t* New Router $item (package $packagename)\n";
    }
);