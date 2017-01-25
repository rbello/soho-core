<?php

// Chargement de la configuration
$config = include_once __DIR__ . '/app/soho.core/config.php';

// Gestion des erreurs
require_once BASE . 'system/app/soho.core/logs.php';
error_reporting($config['debug'] ? E_ALL : 0);

// Librairies tierses
require_once BASE . 'system/app/autoload.php';
require_once BASE . 'system/lib/autoload.php';

### LEAZY LOADING !
### Ne pas tout charger d'un coup !

require_once BASE . 'system/app/soho.core/soho.php';

$GLOBALS['soho'] = new Soho(include BASE . 'data/cache/app/context.cache.php');

// Authentication
#require_once BASE . 'system/lib/soho.security/auth.php';

// Entity manager
#$em = require_once BASE . 'system/lib/soho.core/db.php';

// Tools
#require_once BASE . 'system/lib/soho.core/tools.php';