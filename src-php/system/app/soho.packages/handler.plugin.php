<?php

use \Soho\Packages\PkgContext as PkgContext;



return array(
    "manifest:plugin",
    function (PkgContext $context, $packagename, $filepath, $item, &$contents) {

        $plugins = array();
        
        // Check configuration items
        PkgContext::checkConfigurationItem(
            $contents,
            // Availables attributes
            array(
                'class'         => PkgContext::TYPE_STRING, // Class name of the plugin
                'file'          => PkgContext::TYPE_STRING, // File path of plugin class
                'hooks'         => PkgContext::TYPE_ARRAY   // Event binding hooks
            ),
            // Iterate each attribute
            function (&$value, $pluginname) use ($packagename, $filepath, &$plugins, $context) {
                // And add the package name
                $value['package'] = $packagename;
                // Then resolve classes' methods
                echo "\t* New Plugin '$pluginname' (package $packagename)\n";
                $value['file'] = str_replace('{package.dir}', dirname($filepath), $value['file']);
                // TODO Proteger
                require $value['file'];
                foreach (get_class_methods($value['class']) as $method) {
                    if ($method == 'onStart') continue; // Methods from SohoPlugin interface
                    if (substr($method, 0, 2) == '__') continue; // Hidden methods
                    $plugins[$method] = array(
                        'class' => $value['class'],
                        'package' => $packagename,
                        'file' => $value['file']
                    );
                    echo "\t\t- Method found: {$value['class']}::{$method}()\n";
                }
                // Fetch events 
                if (!isset($value['hooks'])) return;
                foreach ($value['hooks'] as $event => $handler) {
                    echo "\t\t- Hook found: event '{$event}' bound to method {$value['class']}::{$method}()\n";
                    $context->merge('hooks', array(array(
                        'event'  => $event,
                        'class'  => $value['class'],
                        'method' => $handler,
                        'file'   => $value['file']
                    )));
                }
            }
        );

        $context->merge('api', $plugins);

    }
);