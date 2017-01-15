<?php

new MoodelStruct(DatabaseConnectorManager::getDatabase('main'), 'TeamMember_Extra', array(

	'id' => 'int:auto_increment,primary_key',
	'creation' => 'datetime',
	'user' => 'int:foreign_key[TeamMember=id]',

	'type' => 'string',
	'value' => 'serial'

));

?>