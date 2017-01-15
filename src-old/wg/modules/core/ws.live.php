<?php

// Verification du paramètre TIME
if (!isset($_REQUEST['t'])) {
	WG::formatError('Bad Request', 400, 'application/json');
	exit();
}

// Verification du paramètre LIVE EVENTS LIST
if (!isset($_REQUEST['l'])) {
	WG::formatError('Bad Request', 400, 'application/json');
	exit();
}

// AES Decode
if (isset($_SESSION['jcryption']['key'])) {

	// Load AES library
	WG::lib('jcryption/jcryption.php');

	// Get shared AES key
	$key = $_SESSION['jcryption']['key'];

	// Decrypt data
	$_REQUEST['t'] = AesCtr::decrypt($_REQUEST['t'], $key, 256);
	$_REQUEST['l'] = AesCtr::decrypt($_REQUEST['l'], $key, 256);

}

// Début du test Bench
$bench = microtime();
$executed = 'serverTime';

// Temps de référence du client (exprimée en temps serveur)
$tref = intval($_REQUEST['t']);

// Liste des events que le client souhaite suivre
$events = explode('|', $_REQUEST['l']);

// Tableau contenant les données de retour pour le client
$r = array(
	// Servertime est un event par défaut qui sert aussi de référenciel.
	// Il n'est pas déclaré dans aucun manifest.json car il fait partie
	// intégrante du service live.
	'serverTime' => time()
);

// On passe en revue la liste des services Live!
foreach (WG::lives() as $live) {

	// On vérifie que l'event soit bien demandé par le client
	if (!in_array($live['name'], $events)) {
		continue;
	}

	// On enregistre que cet event a été lancé
	$executed .= ',' . $live['name'];

	// Le fichier de script du live
	$file = WG::base($live['script']);

	// On vérifie que ce fichier existe
	if (!is_file($file) || !is_readable($file)) {
		// On renvoi une donnée vide, signalant une erreur
		$r[$live['name']] = null;
		// Dans les logs, on indiquera une erreur par un petit point d'interrogation
		// à droite du nom du service
		$executed .= '!';
		continue;
	}

	// On réécrit les variables pour être certain
	$_LIVE = array();
	$_TIMEREF = $tref;

	// On inclus ce fichier. C'est à lui de remplir la tableau
	// $_LIVE. Le format est libre, c'est ce tableau qui sera renvoyé
	// au client. Les données contenues doivent être sérialisables
	// en JSON.
	// Le script obtient aussi la variable $_TIMEREF qui indique le temps
	// de référence du client, sur le fuseau horaire du serveur. Cette variable
	// permet de renvoyer uniquement du contenu réactualisé depuis cette date.
	require_once $file;

	// Enregistrement des données.
	// Le isset sert au cas où le script aurait supprimé la variable (?)
	// par erreur.
	if (isset($_LIVE)) {
		$r[$live['name']] = $_LIVE;
		unset($_LIVE);
	}

}

// Fin du test Bench
$bench = round(microtime() - $bench, 6);

// Log
@file_put_contents(WG::base('data/live.bench'), "\nLiveService[events=$executed][duration=$bench][version=".filemtime(__FILE__)."]", FILE_APPEND);

// AES Encode
if (isset($key)) {

	// Encode returned data
	echo WG::aesEncrypt(json_encode($r), $key);

	// Stop file execution
	exit();

}

// Envoi au client
echo json_encode($r);

?>