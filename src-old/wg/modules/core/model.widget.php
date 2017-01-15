<?php

interface Widget {

	// @return boolean
	public function display($deleteButton=null);

}

$modelWidget = new MoodelStruct(DatabaseConnectorManager::getDatabase('main'), 'Widget', array(

	'id' => 'int:auto_increment,primary_key',
	'user' => 'int:foreign_key[TeamMember=id]',
	'class' => 'string',
	'params' => 'serial'

));



?>