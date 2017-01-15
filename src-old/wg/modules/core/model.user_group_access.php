<?php

$modelUserGroupAccess = new MoodelStruct(DatabaseConnectorManager::getDatabase('main'), 'UserGroupAccess', array(

	'id' => 'int:auto_increment,primary_key',
	'group' => 'int:foreign_key[UserGroup=id]',
	'access' => 'string',
	'param' => 'serial',
	'value' => 'string'

));

$modelUserGroupAccess->drop();
$modelUserGroupAccess->createTable();

// Donner aux utilisateurs la possibilité d'accéder à leurs home
$modelUserAccess->new
	->set('group', ModelManager::get('UserGroup')->getByName('user')->id)
	->set('access', 'fs')
	->set('param', serialize(array('${HOME}')))
	->save();

// Donner aux développeurs la possibilité d'accéder au repository
$modelUserAccess->new
	->set('group', ModelManager::get('UserGroup')->getByName('dev-evolya')->id)
	->set('access', 'fs')
	->set('param', serialize(array('/dev/evolya/')))
	->save();

?>