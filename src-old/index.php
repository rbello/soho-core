<?php

require_once 'wg/starter.php';

WG::security();

// Important : si on part du principe que l'application est 100% asynchrone,
// le fait de redemander l'application ne devrait arriver qu'une fois.
// Le truc c'est que : si le serveur dispose de cl�s AES, il les utilisent
// automatiquement pour qu'il ne soit pas possible d'utiliser une session
// crypt�e pour obtenir des info d�crypt�es.
// Du coup, si le client obtient une application d�j� logg�e il va peut-�tre
// essayer d'ouvrir une session normale, mais le serveur lui renverra des donn�es
// crypt�es.
// L'application est tout � faire capable de d�marrer � partir d'une session
// logg�e, mais cela pose aussi des probl�mes de s�curit�. Il y a bien le lock
// automatique d'une session charg�e d�j� logg�e, mais bon...
// Cette option apporte beaucoup de s�curit�.
// Pour le dev c'est un peu relou, il est possible de le d�sactiver en m�me
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