<?php

// Verification du paramêtre
if (!isset($_REQUEST['c']) && !isset($_REQUEST['a'])) {
	WG::formatError('Bad Request', 400, 'application/json');
	exit();
}

// AES Decode
if (WG::useAES()) {

	// Load AES library
	WG::lib('jcryption/jcryption.php');

	// Get shared AES key
	$key = $_SESSION['jcryption']['key'];

	// Decrypt data
	$_REQUEST['c'] = AesCtr::decrypt($_REQUEST['c'], $key, 256);

}
else if (!WG::useSSL() && WG::vars('dev_mode') !== true) {
	WG::formatError('Unsecured connexion', 400, 'application/json');
	exit();
}

// Content-type
header('Content-type: application/json');

// Load CLI API
require_once WG::base('cli/cli-import.php');

// Création de l'API CLI
$cli = Default_CLI_Factory();

// Auto-completion
if (isset($_REQUEST['a'])) {
	
	$result = $cli->autocomplete($_REQUEST['a']);
	
}

// Execution de la commande
else {
	ob_start();
	$cli->exec('cli-api ' . $_REQUEST['c']);
	$data = ob_get_contents();
	ob_end_clean();
	
	// On recupère le tableau de résultats
	$result = $cli->result;
	
	// On y ajoute l'utiliser courant (pour le scheme)
	$result['user'] = WG::user()->get('login');
	
	// Ainsi que les données renvoyées par la commande
	$result['data'] = $data;
	
}

// Envoi du résultat
if (WG::useAES()) {
	echo AesCtr::encrypt(json_encode($result), $key, 256);
}
else {
	echo json_encode($result);
}

?>