<?php

require_once 'wg/starter.php';

// Il ne faut pas checker la s�curit� ici, c'est fait apr�s dans executeWebservice()

// Normalement il n'y a pas d'exceptions ici, elles sont d�j� trait�es
WG::executeWebservice('getmusic', false);

?>