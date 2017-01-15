<?php

class Soho_Plugin_Widgets extends Soho_PluginBase implements Iterator {

	/**
	 * Configurations des widgets
	 * @var mixed[][]
	 */
	protected $widgets = array();
	
	/**
	 * Constructeur de la classe.
	 */
	public function __construct() {
		
		// On renseigne le nom du plugin
		parent::__construct('widgets');
		
		// On rends disponible l'API de cette classe directement
		$this->api['widgets'] = $this;

	}

	/**
	 * (non-PHPdoc)
	 * @see Soho_Plugin::init()
	 */
	public function init() {
	
		// On parcours les modules disponibles
		foreach (WG::modules() as $moduleName => $module) {
				
			// On regarde si le manifest déclare des widgets
			if (!isset($module['widgets'])) continue;
			
			// On vérifie que le manifest soit valide
			if (!is_array($module['widgets'])) {
				throw new WGException("Invalid 'widgets' manifest configuration in module `$moduleName`");
			}
			
			// On parcours ces widgets
			foreach ($module['widgets'] as $widgetName => $config) {
				$config['name'] = $widgetName;
				$config['module'] = $moduleName;
				// On enregistre les données du widget
				$this->widgets[$widgetName] = $config;
			}
		
		}

	}

	/**
	 * Utilisable avec : WG::widgets($name)
	 * @param string $name
	 */
	public function __handleCall($name) {
		return array_key_exists($name, $this->widgets) ? $this->widgets[$name] : null;
	}
	
	/**
	 * @return string[]
	 */
	public function types() {
		return array_keys($this->widgets);
	}
	
	/**
	 * Créer un widget à partir d'un dbo.
	 * 
	 * @param Moodel<Widget> $widget
	 * @param mixed[] $config
	 * @return Soho_Widget OK
	 * @return string Erreur
	 */
	public function createByModel(Moodel $widget, $config = array()) {
		
		$class = $widget->get('class');
		$manifest = $this->__handleCall($class);
		
		// TODO Check user rights
		// TODO Recover user config
		
		if (!$manifest) {
			return "widget not supported: $class";
		}
		
		if (!class_exists($class)) {
			require_once WG::base($manifest['script']);
		}
		
		$w = new $class();
		
		if (!($w instanceof Soho_Widget)) {
			return "invalid widget class: $class";
		}
		
		$w->setConfig($config);
		return $w;
		
	}
	
	/**
	 * 
	 * @param string $type
	 * @param Moodel $user
	 * @param mixed[] $config
	 * @return Soho_Widget OK
	 * @return string Erreur
	 */
	public function createByType($type, Moodel $user, $config = array()) {
		
		$manifest = $this->__handleCall($type);
		
		if (!$manifest) {
			return "widget not supported: $type";
		}
		
		// TODO Check user rights
		// TODO Recover user config
		
		if (!class_exists($type)) {
			require_once WG::base($manifest['script']);
		}
		
		$w = new $type();
		
		if (!($w instanceof Soho_Widget)) {
			return "invalid widget class: $type";
		}
		
		$w->setConfig($config);
		return $w;
		
	}

	public function rewind() {
		reset($this->widgets);
	}
	
	public function current() {
		return current($this->widgets);
	}
	
	public function key() {
		return key($this->widgets);
	}
	
	public function next() {
		return next($this->widgets);
	}
	
	public function valid() {
		$key = key($this->widgets);
		return ($key !== NULL && $key !== FALSE);
	}

}

interface Soho_Widget {
	
	/**
	 * @param mixed[] $config
	 */
	public function setConfig($config);
	
	/**
	 * @return string
	 */
	public function html();
	
}

WG::addPlugin(new Soho_Plugin_Widgets());

$modelWidget = new MoodelStruct(DatabaseConnectorManager::getDatabase('main'), 'Widget', array(

	'id' => 'int:auto_increment,primary_key',
	'user' => 'int:foreign_key[TeamMember=id]',
	'class' => 'string',
	'params' => 'serial'

));

?>