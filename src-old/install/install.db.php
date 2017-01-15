<?php

// Init

require_once '../src/wg/starter.php';

// Core

ModelManager::get('TeamMember')->createTable();

ModelManager::get('TeamMember_Extra')->createTable();

ModelManager::get('Log')->createTable();

ModelManager::get('Store')->createTable();

// Activity

WG::module('activity');

ModelManager::get('Activity')->createTable();

ModelManager::get('Activity_Message')->createTable();

ModelManager::get('EmailCronTask')->createTable();

ModelManager::get('Follow')->createTable();

// Dashboard

WG::module('dashboard');

ModelManager::get('Widget')->createTable();

// Strategy

WG::module('strategy');

ModelManager::get('SEOTarget')->createTable();

ModelManager::get('SEOCompetitor')->createTable();

ModelManager::get('SEOQuery')->createTable();

ModelManager::get('SEOCronTask')->createTable();

ModelManager::get('ABTest')->createTable();

// News Reader

WG::module('newsreader');

ModelManager::get('ReaderEntry')->createTable();

echo 'Done';

?>
