<?php

$user = null;

// Security bypass with personal API key
if (isset($_REQUEST['apikey'])) {
	$user = ModelManager::get('TeamMember')->get(array(
		'apikey' => trim($_REQUEST['apikey'])
	));
	if (sizeof($user) !== 1) {
		WG::formatError('Unprocessable Entity (apikey)', 422, 'text/xml');
		exit();
	}
	$user = $user[0];
}

// Regular security
else {
	WG::security();
	if (!WG::checkFlags('u')) {
		self::formatError('Unauthorized', 401, 'text/xml');
		exit();
	}
	$user = WG::user();
}

// Time
if (!isset($_REQUEST['time'])) {
	WG::formatError('Bad Request', 400, 'text/xml');
	exit();
}
$time = intval($_REQUEST['time']);

// Query
$logs = ModelManager::get('Log')->get(
	array('creation' => '>:' . $time),
	array('*'),
	'creation DESC',
	10
);

// Output
echo '<?xml version="1.0" encoding="utf-8" ?>';
echo '<rsp stat="ok" utime="'.time().'">';

$out = '';
foreach ($logs as $log) {
	$out = '<log ctime="'.$log->creation.'" user="'.htmlspecialchars($log->user->name).'"><![CDATA['.htmlspecialchars($log->log).']]></log>' . $out;
}
echo $out;

echo '</rsp>';

// Update user's last connection
if ($user) {
	try {
		$user->set('last_connection', time())->save();
	} catch (Exception $ex) { }
}


?>