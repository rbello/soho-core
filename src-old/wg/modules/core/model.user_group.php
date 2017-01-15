<?php

$modelUserGroup = new MoodelStruct(DatabaseConnectorManager::getDatabase('main'), 'UserGroup', array(

	'id' => 'int:auto_increment,primary_key',
	'user' => 'int:foreign_key[TeamMember=id]',
	'group' => 'string'

));

?>
