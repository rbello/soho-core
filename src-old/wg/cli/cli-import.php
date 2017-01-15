<?php

// Load libraries
require_once WG::base('cli/cli-api.php');
require_once WG::base('cli/cli-ext-base.php');
require_once WG::base('cli/cli-ext-files.php');
require_once WG::base('cli/cli-ext-packages.php');
require_once WG::base('cli/cli-ext-soho.php');
require_once WG::base('cli/cli-ext-stats.php');

require_once WG::base('cli/cli-ext-cesi.php');

// Default Factory
function Default_CLI_Factory() {
	$cli = new Soho_CLI_Cesi();
	$cli->allowIdentityAuto = false;
	$cli->allowIdentityOverride = false;
	return $cli;
}

?>