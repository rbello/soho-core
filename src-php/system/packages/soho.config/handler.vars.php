<?php

use \Soho\Packages\PkgContext as PkgContext;

return array(
    "manifest:vars",
    function (PkgContext $context, $packagename, $filepath, $item, $contents) {

        // Check configuration items
        PkgContext::checkConfigurationItem(
            $contents,
            // Availables attributes
            array(
                'value'         => PkgContext::TYPE_MULTI, // Value of this variable
                'type'          => array('string', 'boolean', 'numeric'), // Value type of this variable
                'description'   => PkgContext::TYPE_STRING, // Description
                'permission'    => PkgContext::TYPE_STRING, // Required permission to view/edit
                'reload'        => PkgContext::TYPE_BOOLEAN // Application reload is required if modified
            ),
            // Iterate each attribute
            function (&$value, $varname) use ($packagename) {
                // And add the package name
                $value['package'] = $packagename;
                echo "\t* New Var '$varname' (package $packagename)\n";
            }
        );

        // Store configuration
        $context->merge('vars', $contents);
    }
);