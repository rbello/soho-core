#!/usr/bin/php
<?php
if (php_sapi_name() != 'cli') exit(-1);

// Init system
include __DIR__ . '/../system/bootstrap.php';
require_once __DIR__ . '/cli/CommandHandler.php';

class EntCommandHandler extends \PHPonCLI\CommandHandler {
    
    /** 
	 * @return null|string|int|mixed
	 */
	public function getCurrentUserID() {
	    return null;
	}
	
	/**
	 * @param string|int|mixed $userID
	 * @param string $object
	 * @param string $permission
	 * @param string $action
	 * @return boolean
	 */
	public function checkPermission($userID, $object, $permission, $action) {
		if ($userID == null) {
			return false;
		}
	    return false;
	}
    
}

// On fabrique une CLI
$cli = new EntCommandHandler();

// On rajoute les commandes des APIs
foreach (glob(__DIR__ . '/../api/*.php') as $file) {
	$classname = substr(basename($file), 0, -4);
	require_once $file;
	$cli->addObject(api($classname));
}

// Execution
$cli->exec($GLOBALS['argv']);

exit(0);