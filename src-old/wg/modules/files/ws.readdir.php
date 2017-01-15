<?php

if (!isset($_REQUEST['node'])) {
	WG::formatError('Bad Request', 400, 'application/json');
	exit();
}

// Les exceptions sont traitées en amont
$api = WG::files();

// On fait un nettoyage du chemin vers le noeud
$node = $api->cleanpath($_REQUEST['node'], true);

// Ici on rajoute une "sécurité" supplémentaire : l'interface doit obligatoirement
// fournir un chemin déjà nettoyé, sinon on refuse systèmatiquement sans fournir plus d'explications
if ($node !== $_REQUEST['node']) {
	WG::formatError('Bad Request', 400, 'application/json');
	exit();
}

// On tente de lister le contenu du répertoire
try {
	$list = $api->ls($node, true);
}

// L'utilisateur n'a pas les droits pour lire ce répertoire
catch (WGFilesSecurityException $ex) {
	WG::formatError('Forbidden', 403, 'application/json');
	exit();
}

// Cette erreur survient que $node n'est pas un répertoire 
catch (WGNotFolderException $ex) {
	WG::formatError('Not Found', 404, 'application/json');
	exit();
}

// Si c'est une erreur inconnue, on laisse passer plus haut
catch (Exception $ex) {
	throw $ex;
}

echo json_encode($list);

?>