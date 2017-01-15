<?php

class WGException extends Exception { }
class WGBootException extends WGException { }
class WGPluginException extends WGBootException { }
class WGSecurityException extends WGException { }
class WGInvalidArgumentException extends WGException { }
// Deprecated ?
class View404Exception extends Exception { }
class ThirdPartyException extends WGException { }


interface Soho_Serializable {

	public function getSerializableUID();

	public function __sleep();

	public function __wakeup();

}

?>