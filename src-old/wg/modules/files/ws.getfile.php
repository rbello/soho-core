<?php

if (!isset($_REQUEST['node'])) {
	WG::formatError('Bad Request', 400, 'application/octet-stream');
	exit();
}

// Les exceptions sont traitées en amont
$api = WG::files();

// On fait un nettoyage du chemin vers le noeud
$node = $api->cleanpath($_REQUEST['node'], false);

// Ici on rajoute une "sécurité" supplémentaire : l'interface doit obligatoirement
// fournir un chemin déjà nettoyé, sinon on refuse systèmatiquement sans fournir plus d'explications
if ($node !== $_REQUEST['node']) {
	WG::formatError('Bad Request', 400, 'application/octet-stream');
	exit();
}

$api = WG::files();

// Verification des autorisations
if (!$api->checkCurrentUserPrivileges($node, 'read', false)) {
	WG::formatError('Forbidden', 403, 'application/octet-stream');
	exit();
}

// TODO Modifier le content-type ?

readfile($api->realpath($node));

?>