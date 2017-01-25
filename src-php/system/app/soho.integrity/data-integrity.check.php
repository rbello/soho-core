<?php

function getDirContents($dir, $path = '', &$results = array()){
    $files = scandir($dir);
    foreach ($files as $key => $value){
        $rpath = realpath($dir.DIRECTORY_SEPARATOR.$value);
        if (!is_dir($rpath)) {
            $results[$path.DIRECTORY_SEPARATOR.$value] = 1;
        }
        else if ($value != '.' && $value != '..') {
            $results[$path.DIRECTORY_SEPARATOR.$value.DIRECTORY_SEPARATOR] = 0;
            getDirContents($rpath, $path.DIRECTORY_SEPARATOR.$value, $results);
        }
    }
    return $results;
}

$base = realpath(dirname(__FILE__) . '/../../../');

$files = getDirContents($base);

foreach ($files as $file => $type) {
    if ($type) {
        $files[$file] = md5_file($base . $file);
    }
}
print_r($files);
$f = realpath(dirname(__FILE__) . '/../../../' . 'system/app/soho.integrity/data.cache.php');
echo "  Write to: $f\n";
file_put_contents($f, '<?php return ' . var_export($files, true) . ';');