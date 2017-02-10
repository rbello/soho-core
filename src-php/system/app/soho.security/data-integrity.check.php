<?php

// Base directory
$base = realpath(dirname(__FILE__) . '/../../../') . '/';

// Include library
include $base . 'system/app/soho.security/data-integrity.lib.php';

// Get current state
$files = getIntegrityChecksums($base . 'system/');

// Load reference state
$ref = include $base . 'data/cache/app/integrity.cache.php';

// Compute delta
$delta = getIntegrityDelta($ref, $files);

print_r($delta);

echo ' ' . sizeof($delta) . " change(s) found.\n";