<?php

// Base directory
$base = realpath(dirname(__FILE__) . '/../../../') . '/';

// Include library
include $base . 'system/app/soho.security/data-integrity.lib.php';

// Get current state
$files = getIntegrityChecksums($base . 'system/');

// Print data
print_r($files);

// Write data to cache file
$f = $base . 'data/cache/app/integrity.cache.php';
echo "  Write to: $f\n";
file_put_contents($f, '<?php return ' . var_export($files, true) . ';');