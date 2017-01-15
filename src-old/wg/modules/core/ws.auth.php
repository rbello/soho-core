<?php

// Logout process
if (isset($_REQUEST['logout'])) {

	// Destroy the session
	WG::logout();

	// TODO Return the right http status code
	//header('Status: 205 Reset Content', 205, true);

	// Return empty response
	echo '{}';

	// Finish
	exit();

}

// Ask for salt
if (isset($_REQUEST['salt'])) {

	// Display salt
	echo json_encode(array('salt' => WG::session()->getSalt()));

	// Finish
	exit();

}

// Get current session
$session = WG::session();

// Can't auth user if the session is allready connected
if ($session->isLogged()) {
	WG::formatError('Conflict (Allready Logged In)', 409, 'application/json');
	exit();
}

// Get fields names
$field_user = $session->get('field_user');
$field_pwd = $session->get('field_pwd');

// Check parameters
if (!isset($_POST[$field_user]) || !isset($_POST[$field_pwd])) {
	WG::formatError('Bad Request', 400, 'application/json');
	exit();
}

// AES Decode
if (isset($_SESSION['jcryption']['key'])) {

	// Load AES library
	WG::lib('jcryption/jcryption.php');

	// Get shared AES key
	$key = $_SESSION['jcryption']['key'];

	// Decrypt data
	$_POST[$field_user] = AesCtr::decrypt($_POST[$field_user], $key, 256);
	$_POST[$field_pwd] = AesCtr::decrypt($_POST[$field_pwd], $key, 256);

}

// Try to login the user in session
$session->auth();

// Pick up the logged account if login was a success
$account = $session->getUser();

// Login failed
if ($account === null) {
	WG::formatError($session->error, 406, 'application/json');
	//WG::formatError('Invalid login or password  '.(isset($_SESSION['jcryption']['key'])?'AES':'').'(' . $_POST[$field_user] . '/' . $_POST[$field_pwd].')', 406, 'application/json');
	exit();
}

// Create data to return to client
$r = json_encode(array(
	'userLogin' => $account->get('login'),
	'userName' => $account->get('name'),
	'sessionAge' => WGCRT_Session::$ttl_session
));

// AES Encode
if (isset($_SESSION['jcryption']['key'])) {

	echo AesCtr::encrypt($r, $key, 256);

	exit();

}

// Return data as JSON
echo $r;

?>
