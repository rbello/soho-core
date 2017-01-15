<?php

new MoodelStruct(DatabaseConnectorManager::getDatabase('main'), 'Log', array(

	'id' => 'int:auto_increment,primary_key',
	'creation' => 'datetime',
	'user' => 'int:foreign_key[TeamMember=id]',

	'target_type' => 'string',
	'target_id' => 'int:foreign_key',
	'target_name' => 'string',

	'action' => 'enum[create,edit,delete,comment,changestatus]',

	'log' => 'text'

));

?>