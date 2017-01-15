<?php

require_once 'wg/starter.php';

// Il ne faut pas checker la sécurité ici, c'est fait après dans executeWebservice()

// No-Cache
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Normalement il n'y a pas d'exceptions ici, elles sont déjà traitées
WG::executeWebservice('http-proxy', false);

?>