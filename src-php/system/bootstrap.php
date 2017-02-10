<?php

// Configuration
$config = include_once __DIR__ . '/app/soho.core/config.php';

// Logs and error management
require_once BASE . 'system/app/soho.core/logs.php';
error_reporting($config['debug'] ? E_ALL : 0);

// Core libraries
require_once BASE . 'system/app/autoload.php';

// Third party libraries
require_once BASE . 'system/lib/autoload.php';

// Soho run
require_once BASE . 'system/app/soho.core/soho.php';
$GLOBALS['soho'] = new Soho(include BASE . 'data/cache/app/context.cache.php');