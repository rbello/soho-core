<?php

set_time_limit(120);
header('Content-type: application/json');

require_once 'wg/starter.php';

// No-Cache
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

try {
	WG::security();
} catch (Exception $ex) {
	exit('{"error":"security"}');
}

echo json_encode(WG::generateKeypair());

?>