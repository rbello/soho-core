<?php

$file = 'src-php/data/cache/app/context.cache.php';

$ctx = include $file;

print_r($ctx);
echo "\nLast reload: " . date('r', filectime($file)) . "\n";