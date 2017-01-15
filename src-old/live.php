<?php

require_once 'wg/starter.php';

// Il ne faut pas checker la s�curit� ici, c'est fait apr�s dans executeWebservice()

// No-Cache
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Normalement il n'y a pas d'exceptions ici, elles sont d�j� trait�es
WG::executeWebservice('live', false);

?>