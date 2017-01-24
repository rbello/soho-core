<?php

use \Soho\Packages\PkgContext as PkgContext;

return array(
    "manifest:plugin",
    function (PkgContext $context, $packagename, $filepath, $item) {
        $item = substr($item, 7, -4);
        echo "\t* New Plugin $item (package $packagename)\n";
     //   require $filepath;
        // TODO Securiser cette partie
        // TODO On pourrait aussi 
       // if (!class_exists($item)) echo "error";
        #$class = new $item();
      //  print_r(get_class_methods($item));
    }
);