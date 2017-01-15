<?php

// On masque toutes les erreurs
error_reporting(0);

// On ferme l'output buffering s'il est activé déjà
while (ob_get_level() > 0) {
	ob_end_clean();
}

// On commence à enregistrer le contenu dans un nouveau buffer
ob_start();

?><!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head>
<title>404 Not Found</title>
</head><body>
<h1>Not Found</h1>
<p>The requested URL /testducrossdomain.php was not found on this server.</p>
</body></html>
<?php

// On renvoi un code d'erreur
header('HTTP/1.0 404 Not Found', 404, true);
// On ferme la connexion 
header("Connection: close");
// On indique la taille des données
header("Content-Length: ".ob_get_length());

// Remote cron log
file_put_contents(
	dirname(__FILE__) . '/wg/data/remote-cron.log',
	date('r', $_SERVER['REQUEST_TIME']) . ' [api "' . PHP_SAPI . '"] [url "/testducrossdomain.php"]'
		. (isset($_SERVER['REMOTE_ADDR']) ? ' [ip "' . $_SERVER['REMOTE_ADDR'] . '" host "'.gethostbyaddr($_SERVER['REMOTE_ADDR']).'"]' : '') . PHP_EOL,
	FILE_APPEND
);

// On fait une fonction qui sera appelée à la fin du script
function shutdown_cc() {
	// On charge l'application
	require_once 'wg/starter.php';
	// On masque toutes les erreurs de nouveau au cas où
	error_reporting(0);
	// On lance le service CRON en mode silent
	try {
		WG::executeCronService(false, true);
	}
	catch (Exception $ex) { }
}

// On enregistre une fonction de shutdown, qui se appelée à la fin
// de la requête du client. L'objectif de tout ça est de ne pas inclure
// le temps d'execution de la task dans la requete, pour que le client
// ne se doute pas qu'il s'agisse d'une requête spéciale mais d'une simple
// erreur 404. 
register_shutdown_function('shutdown_cc');

// On force l'envoi des données
ob_end_flush();
flush();

// Et on coupe la connexion
exit();

?>