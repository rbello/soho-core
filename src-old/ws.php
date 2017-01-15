<?php

require_once 'wg/starter.php';

// No-Cache
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$ws = isset($_REQUEST['w']) ? $_REQUEST['w'] : null;

// Il ne faut pas checker la sécurité ici, c'est fait dans executeWebservice()
// Normalement il n'y a pas d'exceptions ici, elles sont déjà traitées

WG::executeWebservice($ws);

?>