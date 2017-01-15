<?php

require_once 'wg/starter.php';

// Il ne faut pas checker la sécurité ici, c'est fait après dans executeWebservice()

// Normalement il n'y a pas d'exceptions ici, elles sont déjà traitées
WG::executeWebservice('getmusic', false);

?>