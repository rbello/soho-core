<?php

use \Soho\Packages\PkgContext as PkgContext;

return array(
    "file:model.*.php",
    function (PkgContext $context, $packagename, $filepath, $item) {
        $model = substr($item, 6, -4);
        $context->merge('models', array($model => array(
            'package' => $packagename,
            'path' => $filepath
        )));
        echo "Model=$model Package=$packagename File=$item\n";
    }
);