<?php

$modelUser = new MoodelStruct(DatabaseConnectorManager::getDatabase('main'), 'User', array(

	'id' => 'int:auto_increment,primary_key',
	'login' => 'string:unique',
	'name' => 'string',
	'cms_name_map' => 'string',
	'email' => 'string:unique',
	'thumb' => 'string',
	'color' => 'char[7]',
	'last_connection' => 'datetime'

));


$modelUser->drop();
$modelUser->createTable();

$modelUser->new
	->set('login', 'remi')
	->set('name', 'Rémi')
	->set('email', 'remi@evolya.fr')
	->save();

?>