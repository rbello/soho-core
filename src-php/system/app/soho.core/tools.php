<?php

/**
 * API factory, with singleton principe.
 */
function api($className) {
    $className = "\\API\\{$className}";
    if (!isset($GLOBALS['__API__']))
        $GLOBALS['__API__'] = array();
    if (!isset($GLOBALS['__API__'][$className]))
        $GLOBALS['__API__'][$className] = new $className();
    return $GLOBALS['__API__'][$className];
}

/**
 * Run a code an catch exceptions and error reportings.
 */
function capture(\Closure $try, \Closure $catch) {
    // Setup handlers for catching error reporting
    set_error_handler(function ($errno, $errstr, $errfile, $errline, $errcontext) use ($catch) {
        $errstr = trim($errstr);
        if (preg_match('/(.+)must be(.+)given/i', $errstr)) {
            $ex = new InvalidArgumentException($errstr, $errno);
        }
        else {
            $ex = new Exception($errstr, $errno);
        }
        $catch($ex);
    }, E_ALL | E_STRICT);
    // Run the $try function
    try {
        return $try();
    }
    // Catch all exceptions
    catch (\Exception $ex) {
        return $catch($ex);
    }
    finally {
        restore_error_handler();
    }
}

/**
 * Get the real type of an object, eaven for arrays.
 */
function get_type($var) {
    // Cas particulier des tableaux
    if (is_array($var)) {
        // Tableau vide : indéterminé
        if (empty($var)) {
            return 'mixed[]';
        }
        // On va chercher le type du tableau
        $type = null;
        foreach ($var as $k => &$v) {
            // C'est un tableau associatif
            if (!is_int($k)) return 'struct';
            // On a plusieurs types différents
            $t = is_object($v) ? get_class($v) : gettype($v);
            if ($type != null && $type != $t) return 'mixed[]';
            $type = $t;
        }
        // On a déterminé le type du tableau
        return $type . '[]';
    }
    // Cas particulier des objets : on donne le nom de la classe
    else if (is_object($var)) {
        return 'object:' . get_class($var);
    }
    // Sinon, on renvoie le type
    return gettype($var);
}