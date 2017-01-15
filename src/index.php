<?php

require_once 'soho/starter.php';

WG::security();

// Important : si on part du principe que l'application est 100% asynchrone,
// le fait de redemander l'application ne devrait arriver qu'une fois.
// Le truc c'est que : si le serveur dispose de clés AES, il les utilisent
// automatiquement pour qu'il ne soit pas possible d'utiliser une session
// cryptée pour obtenir des info décryptées.
// Du coup, si le client obtient une application déjà loggée il va peut-être
// essayer d'ouvrir une session normale, mais le serveur lui renverra des données
// cryptées.
// L'application est tout à faire capable de démarrer à partir d'une session
// loggée, mais cela pose aussi des problèmes de sécurità. Il y a bien le lock
// automatique d'une session chargée déjà loggée, mais bon...
// Cette option apporte beaucoup de sécurité.
// Pour le dev c'est un peu relou, il est possible de le désactiver en même
// temps que l'ouverture automatique d'une session AES dans le code javascript
// (bypasser le code d'openAES et lancer un welcome() directement).
if (WG::vars('dev_mode') !== true) {
	WG::logout();
}

// No-Cache
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

WG::core();

?>