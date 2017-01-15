<?php

new MoodelStruct(DatabaseConnectorManager::getDatabase('main'), 'EmailCronTask', array(

	'id' => 'int:auto_increment,primary_key',
	'to' => 'string',
	'from' => 'string',
	'creation' => 'datetime',
	'title' => 'string',
	'contents' => 'text'

));

?>