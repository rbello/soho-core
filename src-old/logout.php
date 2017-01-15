<?php

require_once 'wg/starter.php';

// No-Cache
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

WG::logout();

require_once WG::base('modules/core/template.logout.html');

?>