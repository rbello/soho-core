<?php

// Emplacement du système
define('BASE', realpath(__DIR__ . '/../') . '/');

// Configuration par défaut
$config = include BASE . 'config/config.default.php';
$config['host'] = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : (isset($_SERVER['C9_HOSTNAME']) ? $_SERVER['C9_HOSTNAME'] : 'localhost');

// Surchargée par la configuration pour ce serveur
$file = BASE . "config/config.{$config['host']}.php";
if (file_exists($file)) {
    $config = array_merge($config, include $file);
}
unset($file);

return $config;