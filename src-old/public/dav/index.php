<?php

if ($_SERVER['SERVER_NAME'] !== 'code.evolya.fr') {
	header('Redirect: https://code.evolya.fr/');
	exit();
}

// Fix pour le host
$_SERVER['SERVER_NAME'] = 'workshop.evolya.fr';

// Fix pour le basename du serveur webdav
$_SERVER['SCRIPT_NAME'] = '/';

// Fix pour le chemin
if (empty($_SERVER['REQUEST_URI'])) {
	$_SERVER['REQUEST_URI'] = '/';
}

require_once dirname(__FILE__) . '/../../webdav.php';

?>
