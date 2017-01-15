<?php

$account = WG::user();

// Pas obligatoire car vérifié par WG vu le manifest.json, mais dans le doute...
if (!$account) {
	WG::formatError('Unauthorized', 401, 'application/json');
	exit();
}

// Tableau de  sortie
$r = array();

// Données de la session

$r['sessiondata'] = array(
	'userLogin' => $account->login,
	'userPwdHash' => substr($account->password, 0, 15),
	'userName' => $account->name,
	'userAvatar' => $account->thumb,
	'sessionAge' => WG::vars('session_age'), // TODO Passer par WGCRT non ? Ou alors modifier WGCRT...
);

// Sychronisation temporelle

$r['time'] = array(
	'serverTimestamp' => $_SERVER['REQUEST_TIME'],
	'serverTimezone' => date_default_timezone_get(),
	'serverGMT' => date('O', $_SERVER['REQUEST_TIME']),
	'serverDateGMT' => str_replace('~', 'GMT', date('D M d Y H:i:s ~O', $_SERVER['REQUEST_TIME'])) . ' (CEST)',
	'serverDateW3C' => date(DATE_W3C, $_SERVER['REQUEST_TIME'])
);

// Settings

$r['settings'] = array(
	'lang' => 'en',
	'autolock' => 20 // en minutes
);

// Menus
// TODO Supprimer quand la partie JS supportera la gestion des vues

$r['menu'] = array();

foreach (WG::menus() as $menu) {
	// Security
	if (isset($menu['requireFlags'])) {
		if (!WG::checkFlags($menu['requireFlags'])) {
			continue;
		}
	}
	// Create item
	$tmp = array(
		'label' => $menu['label'],
		'view' => $menu['view'],
		'module' => $menu['module']
	);
	// Fetch sub items
	if (isset($menu['sub'])) {
		$tmp['subs'] = array();
		foreach ($menu['sub'] as $sub) {
			// Security
			if (isset($sub['requireFlags'])) {
				if (!WG::checkFlags($sub['requireFlags'])) {
					continue;
				}
			}
			// Create and store item
			$tmp['subs'][] = array(
				'label' => $sub['label'],
				'view' => $sub['view']
			);
		}
		if (sizeof($tmp['subs']) < 1) {
			unset ($tmp['subs']);
		}
	}
	// Store item
	$r['menu'][] = $tmp;
}
unset($tmp);

// Les vues

$r['views'] = array();

foreach (WG::views() as $viewName => $viewData) {
	// Security
	if (isset($viewData['requireFlags'])) {
		if (!WG::checkFlags($viewData['requireFlags'])) {
			continue;
		}
	}
	// Contient les paramètres de la vue
	$a = array();
	// Model de distribution de la page
	if (isset($viewData['distribution'])) {
		$a['dist'] = $viewData['distribution'];
	}
	// Sauvegarde des infos de la vue
	$r['views'][$viewName] = $a;
}

// AES

if (isset($_SESSION['jcryption']['key'])) {

	// Load AES library
	WG::lib('jcryption/jcryption.php');

	// Encode returned data
	echo WG::aesEncrypt(json_encode($r), $_SESSION['jcryption']['key']);

	exit();

}

// On renvoi le résultat
echo json_encode($r);

?>