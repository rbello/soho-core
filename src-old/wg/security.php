<?php

// TODO Où se trouve le set de la custom security policy de WGCRT_Session ?

WG::lib('wgcrt.php');

// Configuration de la session
WGCRT_Session::$sendNotificationMail = true;
WGCRT_Session::$allowAgentChange = false;
WGCRT_Session::$allowIPChange = false;
WGCRT_Session::$debugMode = WG::vars('dev_mode') === true;
WGCRT_Session::$publicRealm = WG::vars('public_realm');

// Log
if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] != '/live.php') {
	WGCRT_Session::syslog_record();
}

// Debug
//$_SERVER['GEOIP_COUNTRY_CODE'] = 'ES';

// Spécifique pour moi : système de protection qui impose de rentrer un mot de passe
// d'un keyring si la provenance n'est pas la france.
// Le realm du keyring est: TravelMode
if (
		WG::vars('dev_mode') !== true && // Uniquement en production
		!defined('WGCRT_DISABLE_KEYRING') && // Cette constante permet de désactiver ce système
		array_key_exists('GEOIP_COUNTRY_CODE', $_SERVER) // Et si le module GEOIP est présent
	) {
	
	// On s'assure que l'ID de keyring ne soit pas déjà diffusé
	unset($keyringid);
	
	// On enregistre un listener qui sera appelé quand une session expirera, afin
	// de remettre à zero le timestamp dans le coffre
	WGCRT_Session::registerGcFunction(function ($event, WGCRT_Session $session) {
			
		// On ne s'interesse qu'aux sessions qui expirent
		if ($event != 'expire' && $event != 'destroy') return;
			
		// On recupère les variables de la session
		$vars = $session->get('user_vars');
			
		// On vérifie l'existence du keyring id
		if (!array_key_exists('keyring_id', $vars)) {
			return;
		}
			
		// Chemin vers le fichier de keyring
		$keyringfile = WG::base("data/keyrings/{$vars['keyring_id']}.kr");
			
		// Le fichier n'existe plus, on ignore cet event
		if (!is_file($keyringfile)) {
			return;
		}
			
		// On lit le fichier de keyring
		$lock = @json_decode(file_get_contents($keyringfile), true);
			
		// Helas, le fichier est illisible. On ne fait rien de spécial, ce n'est pas une
		// erreur très grave.
		if (!is_array($lock)) {
			return;
		}
			
		// On reset timestamp
		$lock['lasttime'] = 0;
			
		// Et on enregistre le fichier
		@file_put_contents($keyringfile, json_encode($lock));
			
	});
	
	// Lancement du système!
	// Le système ne s'applique que pour les provenance différente du pays "normal"
	if ($_SERVER['GEOIP_COUNTRY_CODE'] != 'FR') {
		
		// Ce fix permet - avec le code du htacces - de corriger un problème avec PHP utilisé en CGI
		WGCRT_Session::fixHttpBasicAuthentication();
		
		// Des informations d'identification on été rentrées
		if (array_key_exists('PHP_AUTH_USER', $_SERVER) && !empty($_SERVER['PHP_AUTH_USER'])) {
			
			// On détermine le fichier de keyring de l'utilisateur
			$fileid = md5($_SERVER['PHP_AUTH_USER'] . ':TravelMode');
			$filepath = WG::base("data/keyrings/$fileid.kr");
			
			// Le fichier de keyring existe
			if (is_file($filepath) && is_readable($filepath)) {
				
				// On lit le fichier de keyring
				$lock = @json_decode(file_get_contents($filepath), true);
				
				// Le fichier est illisible, on refuse toutes connexions
				if (!is_array($lock)) {
					header('HTTP/1.0 500 Internal Server Error', true, 500);
					exit("500 Internal Server Error");
				}
				
				// On regarde si la session a expirée
				if ($_SERVER['REQUEST_TIME'] - $lock['lasttime'] > WGCRT_Session::$ttl_session) {
					// Si oui, on incrémente le curseur
					$lock['cursor']++;
				}
				
				// Si aucun mot de passe n'a été envoyé, on indique l'indice de la clé
				if (empty($_SERVER['PHP_AUTH_PW'])) {
					header('WWW-Authenticate: Basic realm="Enter password #'.($lock['cursor'] === 0 ? '0' : $lock['cursor']).'"');
					header('HTTP/1.0 401 Unauthorized', true, 401);
					exit("401 Unauthorized");
				}				
				
				// On regarde si la clé existe
				if (!isset($lock['keys'][$lock['cursor']])) {
					header('HTTP/1.0 520 Expired Key File', true, 520);
					exit('Error 520 Expired Key File');
				}
				
				// On prépare le mot de passe renvoyé par l'utilisateur
				$password = sha1($_SERVER['PHP_AUTH_USER'] . ':TravelMode:' . $_SERVER['PHP_AUTH_PW']);
				
				// Debug
				//echo '[' . $_SERVER['PHP_AUTH_PW'] . " : $password = " . $lock['keys'][$lock['cursor']] . "]"; 
				
				// Password mismatch
				if ($password !== $lock['keys'][$lock['cursor']]) {
					header('WWW-Authenticate: Basic realm="Invalid password #'.($lock['cursor'] === 0 ? '0' : $lock['cursor']).'"');
					header('HTTP/1.0 401 Unauthorized', true, 401);
					exit("401 Unauthorized");
				}
				
				// Ici on a validé le mot de passe et l'utilisateur, on va mettre à jour le timestamp qui
				// est dans le coffre
				$lock['lasttime'] = $_SERVER['REQUEST_TIME'];
				
				// Et on enregistre le fichier du coffre mis à jour
				if (!file_put_contents($filepath, json_encode($lock))) {
					header('HTTP/1.0 500 Internal Server Error', true, 500);
					exit("500 Internal Server Error");
				}
				
				// On enregistre l'ID du keyring pour la callback de fermeture de session
				$keyringid = $fileid;
				
				// On libère la mémoire
				unset($fileid, $filepath, $lock, $password);
				
			}
			
			// Le fichier n'existe pas, le service n'est pas disponible pour cet utilisateur
			else {
				header('WWW-Authenticate: Basic realm="Invalid user"');
				header('HTTP/1.0 401 Unauthorized', true, 401);
				exit("401 Unauthorized");
			}

		}
		
		// Aucune saisie
		else {
			header('WWW-Authenticate: Basic realm="Enter your login, leave the password blank"');
			header('HTTP/1.0 401 Unauthorized', true, 401);
			exit("401 Unauthorized");
		}
		

	}	
	
}

// On initialise le système de session
WGCRT_Session::init(true);

// On ouvre la session
// Si des informations d'authentification sont enregistrées,
// les informations sont automatiquement restaurées.
$session = WGCRT_Session::getSession(
	defined('WGCRT_SESSION_TYPE') ? WGCRT_SESSION_TYPE : 'http'
)->start();

// On enregistre la session dans WG
WG::session($session);

// Deuxième étape de la protection TravelMode : on enregistre l'ID du keyring
// dans la session, pour que la destruction de la session entraine un reset du lockfile
if (isset($keyringid)) {
	$_SESSION['keyring_id'] = $keyringid;
	unset($keyringid);
}

?>