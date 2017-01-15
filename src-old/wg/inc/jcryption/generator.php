<?php

if (!function_exists('openssl_random_pseudo_bytes')) {
	echo "\n\t\t\t Error: OpenSSL extension missing";
	return;
}

if (!function_exists('gmp_strval')) {
	echo "\n\t\t\t Error: GMP extension missing";
	return;
}

set_time_limit(0);

require_once 'jCryption.php';

$keyLength = 1024;
$jCryption = new jCryption();

$numberOfPairs = 100;
$arrKeyPairs = array();

for ($i=0; $i < $numberOfPairs; $i++) {
	$arrKeyPairs[] = $jCryption->generateKeypair($keyLength);
}

$file = array();
$file[] = '<?php';
$file[] = '$arrKeys = ';
$file[] = var_export($arrKeyPairs, true);
$file[] = ';';

file_put_contents(dirname(__FILE__).'/'.$numberOfPairs . "_". $keyLength . "_keys.inc.php", implode("\n", $file));

echo "\n\t\t\t New AES keyfile generated.";

?>