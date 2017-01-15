<?php

// Define SoHo base
define('SOHO_BASE', realpath(dirname(__FILE__)) . '/');

// Set include path with include directory and pear
set_include_path(
	get_include_path()
	. PATH_SEPARATOR . SOHO_BASE . 'inc/'
	. PATH_SEPARATOR . SOHO_BASE . 'inc/Pear/'
);

// Require functions library
require_once 'inc/functions.php';

// Require WG library
require_once 'inc/WG.php';

// Disable magic quotes runtime
@set_magic_quotes_runtime(false);
ini_set('magic_quotes_runtime', 0);
if (get_magic_quotes_gpc()) {
	if (!function_exists('stripslashes_deep')) {
		function & stripslashes_deep(&$value) {
			if (is_array($value)) {
				$value = array_map('stripslashes_deep', $value);
			}
			else if (is_string($value)) {
				$value = stripslashes($value);
			}
			return $value;
		}
	}
	global $_GET, $_POST, $_REQUEST, $_COOKIE, $_SESSION, $_FILE;
	stripslashes_deep($_GET);
	stripslashes_deep($_POST);
	stripslashes_deep($_REQUEST);
	stripslashes_deep($_COOKIE);
	stripslashes_deep($_SESSION);
	stripslashes_deep($_FILE);
}

// Start WG
try {
	WG::boot('./boot.conf');
}
catch (Exception $ex) {
	echo "<p><b>Unable to start SoHo</b>: " . get_class($ex) . " with message " . $ex->getMessage().'</p>';
	exit();
}

?>