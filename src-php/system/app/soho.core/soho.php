<?php

class Soho {

	private static $instance;

	// Tableau contenant le contexte applicatif
	private $context = array();
	
	// Liste des plugins chargés (instanciés)
	private $plugins = array();
	
	private $listeners = array();

	public function Soho(&$context) {
		$this->context = $context;
		self::$instance = $this;
	}

	public function getContext() {
		return $this->context;
	}

	public static function notify($event, $args = null) {
		if (!isset(self::$event->listeners[$event])) return;
		foreach (self::$event->listeners[$event] as $listener) {
			call_user_func($listener, $args);
		}
	}

	public static function on($event, $listener) {
		if (!isset(self::$event->listeners[$event])) {
			self::$event->listeners[$event] = array();
		}
		self::$event->listeners[$event][] = $listener;
	}

	public static function __callStatic($func, $args) {
		// Fetch plugins
		foreach (self::$instance->context['plugins'] as $method => &$plugin) {
			if ($method != $func) continue;
			
			$class = $plugin['class'];
			echo "Found API method: {$class}::{$method}()\n";
			// Auto-load
			if (!isset(self::$instance->plugins[$class])) {
				echo "Load plugin: {$class}\n";
				// TODO Proteger
				include $plugin['file'];
				$p = self::$instance->plugins[$class] = new $class;
				$p->onStart(self::$instance);
			}
			// Call method
			return call_user_func_array(array($class, $method), $args);
		}
		// Method not found
		throw new \Exception("Unable to find API method: Soho::{$func}()");
	}

}

interface SohoPlugin {
	public function onStart(Soho $soho);
}