<?php

// On force l'initialisation de cet host là, car ce fichier peut être appelé par le plannificateur de tâche OVH,
// et dans ce cas c'est la config de dev qui s'appliquait !
$_SERVER['SERVER_NAME'] = 'workshop.evolya.fr';

// Initialise WG
require_once 'wg/starter.php';

// Content-type
header("Content-type: text/plain");

// Log
WG::lib('wgcrt.php');
WGCRT_Session::syslog_record();

// Remote cron log
file_put_contents(
	dirname(__FILE__) . '/wg/data/remote-cron.log',
	date('r', $_SERVER['REQUEST_TIME']) . ' [api "' . PHP_SAPI . '"] [url "/cron.php"]'
		. (isset($_SERVER['REMOTE_ADDR']) ? ' [ip "' . $_SERVER['REMOTE_ADDR'] . '" host "'.gethostbyaddr($_SERVER['REMOTE_ADDR']).'"]' : '') . PHP_EOL,
	FILE_APPEND
);

// Normalement il n'y a pas d'exceptions ici, elles sont d�j� trait�es
WG::executeCronService(
	WG::vars('dev_mode') === true, // Afficher le r�sultat au client
	true // Logger
);

?>