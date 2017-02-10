<?php

function getIntegrityChecksums($dir, $path = '', &$results = array()) {
    $p = '.'; $pp = '..';
    $files = scandir($dir);
    // Fetch files
    foreach ($files as $key => $value) {
        // Real path
        $rpath = realpath($dir.DIRECTORY_SEPARATOR.$value);

        // Files
        if (!is_dir($rpath)) {
            // Compute MD5 checksum
            $results[$path.DIRECTORY_SEPARATOR.$value] = md5_file($rpath);
        }
        
        // Sub-directories
        else if ($value != $p && $value != $pp) {
            $results[$path.DIRECTORY_SEPARATOR.$value.DIRECTORY_SEPARATOR] = 0;
            getIntegrityChecksums($rpath, $path.DIRECTORY_SEPARATOR.$value, $results);
        }
    }
    return $results;
}

function getIntegrityDelta(&$reference, $copy) {
    $delta = array();
    foreach ($reference as $file => $checksum) {
        if (!isset($copy[$file])) {
            $delta[$file] = 'removed';
            continue;
        }
        if ($copy[$file] != $checksum) {
            $delta[$file] = 'modified';
        }
        unset($copy[$file]);
    }
    foreach ($copy as $file => $checksum) {
        $delta[$file] = 'added';
    }
    return $delta;
}