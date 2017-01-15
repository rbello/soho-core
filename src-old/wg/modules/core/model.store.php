<?php

$modelStore = new MoodelStruct(DatabaseConnectorManager::getDatabase('main'), 'Store', array(

	'id' => 'int:auto_increment,primary_key',
	'name' => 'char[20]:index',
	'item' => 'char[60]',
	'update' => 'int',
	'data' => 'serial'

));

?>