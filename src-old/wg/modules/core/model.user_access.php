<?php

$modelUserAccess = new MoodelStruct(DatabaseConnectorManager::getDatabase('main'), 'UserAccess', array(

	'id' => 'int:auto_increment,primary_key',
	'user' => 'int:foreign_key[User=id]',
	'access' => 'string',
	'param' => 'serial',
	'value' => 'string'

));

$modelUserAccess->drop();
$modelUserAccess->createTable();

// Donner à l'utilisateur la possibilité de se connecter à l'interface web
$modelUserAccess->new
	->set('group', ModelManager::get('User')->getByEmail('remi@evolya.fr')->id)
	->set('access', 'web-ui-access')
	->set('value', sha1('toor')) // Mot de passe
	->save();

// Donner à l'utilisateur la possibilité d'utiliser une clé d'API
$modelUserAccess->new
	->set('group', ModelManager::get('User')->getByEmail('remi@evolya.fr')->id)
	->set('access', 'api-key')
	->set('value', '4478vbrf4165dzs41dcsnujDZ48c8z6NJfdde485')
	->save();


?>