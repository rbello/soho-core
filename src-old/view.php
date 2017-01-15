<?php

require_once 'wg/starter.php';

// No-Cache
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$view = isset($_REQUEST['v']) ? $_REQUEST['v'] : null;

try {

	WG::security();
	if (WG::$log_boot) {
		WG::bootlog("display view $view");
	}
	echo WG::view($view);

}
catch (Exception $ex) {

	wgcrt_log_exception($ex);
	
	if (WG::$log_boot) {
		WG::bootlog($ex);
	}

	if ($ex instanceof WGSecurityException) {
		header('HTTP/1.0 401 ' . $ex->getMessage(), true, 401);
	}
	else if ($ex instanceof View404Exception) {
		header('HTTP/1.0 404 View Not Found', true, 404);
	}
	else {
		header('HTTP/1.0 500 Internal Server Error', true, 500);
	}
	echo '<h1>Error</h1><h2>'.get_class($ex).'</h2><p>'.htmlspecialchars($ex->getMessage()).'</p>';

}

?>