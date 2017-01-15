<?php

// Init

require_once '../src/wg/starter.php';
require_once '../src/wg/inc/wgcrt.php';

// Drop

$model = ModelManager::get('TeamMember');
$model->drop();
$model->createTable();

// Users

$model->new
	->set('name', 'Root')
	->set('login', 'root')
	->set('cms_name_map', '')
	->set('email', 'contact@evolya.fr')
	->set('flags', 'usSaAcbpxZ')
	->set('thumb', 'default.png')
	->set('color', 'red')
	->save();

$model->new
	->set('name', 'Rémi')
	->set('login', 'remi')
	->set('cms_name_map', 'adm_remi')
	->set('email', 'remi@evolya.fr')
	->set('flags', 'usSacbp')
	->set('thumb', 'remi.png')
	->set('color', '#d3f0c6')
	->set('password', sha1('toor'))
	->set('apikey', WGCRT_Session::randomStr(48))
	->save();

$model->new
	->set('name', 'SoHo')
	->set('login', 'soho')
	->set('email', 'soho@evolya.fr')
	->set('flags', '')
	->set('thumb', 'default.png')
	->set('color', '')
	->save();

echo 'Done';

?>
