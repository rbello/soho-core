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
		// Search into the list of hooks into manifest files
		foreach (self::$instance->context['hooks'] as $k => &$hook) {
			// Not for that event
			if ($hook['event'] != $event) continue;
			// Auto-load if neccessary
			if (!isset(self::$instance->plugins[$hook['class']])) {
				// Load module
				echo "Load plugin {$hook['class']} due to hook method {$hook['method']} on event $event\n";
				$plugin = self::$instance->loadPlugin($hook['class'], $hook['file']);
			}
			else $plugin = self::$instance->plugins[$hook['class']];
			// Bind event
			//self::$instance->on($event, array($plugin, $hook['method']));
			// Cleanup
			unset(self::$instance->context['hooks'][$k]);
		}
		echo "Propagate event: $event\n";
		// Search into the registred listeners list
		if (isset(self::$instance->listeners[$event])) {
			foreach (self::$instance->listeners[$event] as $listener) {
				call_user_func($listener, $args);
			}
		}
		/*$tmp = array_keys(self::$instance->context['hooks'], $event, true);
		if (sizeof($tmp) > 0) {
			foreach ($tmp as $handler) {
				list($plugin, $class, $method) = explode('::', $handler, 3);
				// Auto-load
				if (!isset(self::$instance->plugins[$class])) {
					echo "Load plugin $plugin due to hook $class::$method on event $event\n";
					$ctx = self::$instance->context['plugins'][$plugin];
					print_r($ctx);
					//
				}
			}
		}*/
	}

	public static function on($event, $listener) {
		if (!isset(self::$instance->listeners[$event])) {
			self::$instance->listeners[$event] = array();
		}
		self::$instance->listeners[$event][] = $listener;
	}

	private function loadPlugin($class, $file) {
		echo "Load plugin: {$class}\n";
		// TODO Proteger
		include $file;
		$p = self::$instance->plugins[$class] = new $class;
		$p->onStart($this);
		return $p;
	}

	public static function __callStatic($func, $args) {
		// Fetch plugins
		foreach (self::$instance->context['api'] as $method => &$plugin) {
			if ($method != $func) continue;
			$class = $plugin['class'];
			echo "Found API method: {$class}::{$method}()\n";
			// Auto-load
			if (!isset(self::$instance->plugins[$class])) {
				self::$instance->loadPlugin($class, $plugin['file']);
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