<?php

spl_autoload_register(function ($class) {
    
    if (substr($class, 0, 1) == '\\') $class = substr($class, 1);
    if (!strpos($class, '\\')) return;
    
    list($namespace, $class) = explode('\\', $class, 2);
    
    if ($namespace == 'Models') {
        if (file_exists(__DIR__."/../models/{$class}.php")) {
            require_once __DIR__."/../models/{$class}.php";
        }
    }
    else if ($namespace == 'API') {
        if (file_exists(__DIR__."/../api/{$class}.php")) {
            require_once __DIR__."/../api/{$class}.php";
        }
    }
});