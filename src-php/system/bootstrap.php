<?php

// Chargement de la configuration
$config = include_once __DIR__ . '/lib/soho.core/config.php';

// Niveau d'erreur
error_reporting($config['debug'] ? E_ALL : 0);

// Librairies tierses
require_once BASE . 'system/lib/soho.core/autoload.php';
require_once BASE . 'system/lib/3rdParty/autoload.php';

// Authentication
require_once BASE . 'system/lib/soho.security/auth.php';

// Entity manager
$em = require_once BASE . 'system/lib/soho.core/db.php';

// Tools
require_once BASE . 'system/lib/soho.core/tools.php';