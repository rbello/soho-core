<?php

if (PHP_SAPI === 'cli') {

	$here = dirname(__FILE__);
	
	// Enable color interface
	// http://pueblo.sourceforge.net/doc/manual/ansi_color_codes.html
	ini_set('cli_server.color', true);

	// Initialize WG
	require_once $here . '/../starter.php';
	WG::lib('wgcrt.php');

	// Load API
	require_once $here . '/cli-import.php';
	
	// Init session
	WGCRT_Session::$debugMode = WG::vars('dev_mode') === true;
	WGCRT_Session::init(false);
	$session = WGCRT_Session::getSession('cli')->start();
	
	// On enregistre la session dans WG
	WG::session($session);
	
	// On met un niveau d'erreur adapté
	error_reporting(E_ALL ^ E_DEPRECATED ^ E_WARNING ^ E_NOTICE);

	// Create CLI API
	$cli = Default_CLI_Factory();
	
	// Config
	$cli->allowIdentityAuto = true;
	$cli->allowIdentityOverride = false;
	$cli->supportHashedPassword = false;
	$cli->setStylesEnabled(true);
	
	// Execute command
	// Ici on implode le tableau car PHP ne gère pas les guillements "" et exec() va faire
	// ce traitement si on lui passe une string.
	$cli->exec(implode(' ', $argv));
	
	// Return result code
	exit($cli->result['returnCode']);

}

?>