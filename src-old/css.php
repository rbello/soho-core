<?phprequire_once 'wg/starter.php';WG::security();if (!WG::checkFlags('u')) {	WG::formatError('Unauthorized', 401, 'text/css');	exit();}try {	header('Content-type: text/css');	echo WG::stylesheets();}catch (Exception $ex) {	WG::formatError($ex->getMessage(), 500, 'text/css');	wgcrt_log_exception($ex);}?>