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

// Check arguments
if (!isset($_POST['key'])) {
	echo '{"error":"missing KEY argument"}';
	return;
}

if (!isset($_SESSION['jcryption']['e'])) {
	echo '{"error":"generate keypair first"}';
	return;
}

if (isset($_SESSION['jcryption']['key'])) {
	// TODO Le handshake a déjà été fait = erreur ?
}

echo json_encode(WG::handshake($_POST['key']));

?>