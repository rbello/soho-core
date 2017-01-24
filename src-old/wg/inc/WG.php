<?php



/**

 * WG est un conteneur d'application qui repose sur un système de modules d'après

 * une configuration utilisant des fichiers JSON.

 * 

 * WG gêre les tâches suivantes :

 * 	- Modules (configuration par surcharge)

 *  - Sécurité et ACL

 *  - Webservices 

 *  - IHM & Vues

 *  - CRON jobs

 *  - Live service

 * 

 * WG est un conteneur léger, qui se charge au fur et à mesure qu'il est utilisé.

 * Par défaut, il ne gêre pas l'authentification des utilisateurs. Cette tâche

 * revient à la session (WGCRT_Session) qui doit être inscrite dans WG.

 * 

 * @version 17

 */

class WG {



	/**

	 * La dernière instance de WG créée.

	 * @var WG

	 */

	protected static $instance = null;



	/**

	 * Répertoire des sources de WG.

	 * @var string

	 */

	protected static $wgfolder = null;



	/**

	 * Répertoire de base de l'application WG.

	 * @var string

	 */

	protected static $wgbase = null;

	

	/**

	 * Contient l'instance de l'utilisateur système.

	 * @var Moodel<TeamMember>|null

	 */

	protected static $sysuser = null;



	/**

	 * Le niveau par défaut pour les flags qui ne spécifient pas de niveau.

	 * @var int

	 */

	public static $defaultFlagLevel = 300;



	/**

	 * Logs du boot de WG.

	 * @var array[]

	 */

	protected static $bootlogs = array();



	/**

	 * Indiquer si le boot de WG doit être loggé

	 * @var boolean

	 */

	public static $log_boot = true;



	/**

	 * Tableau contenant la configuration manifest principale de l'applications.

	 * @var mixed[]

	 */

	protected $manifest = null;



	/**

	 * Tablaeu contenant la configuration des modules de l'application.

	 * @var mixed[]

	 */

	protected $modules = null;



	/**

	 * Tableau contenant la liste des modules déjà chargés.

	 * @var string[]

	 */

	protected $loaded = array();



	/**

	 * Tableau contenant la configuration des vues de l'applications.

	 * @var mixed[]

	 */

	protected $views = array();



	/**

	 * Le nom de l'host pour la configuration.

	 * @var string

	 */

	protected $host = null;



	/**

	 * Connecteur de la base de données.

	 * @var DatabaseConnector

	 */

	protected $db = null;



	/**

	 * Les variables de configuration de l'application.

	 * @var mixed[]

	 */

	protected $vars = array();



	/**

	 * Indique si le cache d'application est activé ou non.

	 * @var boolean

	 */

	protected $appCache = false;



	/**

	 * Contient la session active, ou NULL si aucune session n'a été démarrée et enregistrée avec session().

	 * @var WGCRT_Session

	 */

	protected $session = null;

	

	/**

	 * Contient tous les plugins intégrés à WG.

	 * @var Soho_Plugin[] $plugins

	 */

	private static $plugins = array();

	

	############################################  L I F E   C Y C L E



	/**

	 * Lance la phase d'initilisation de WG.

	 * 

	 * @param string $bootFile

	 * @throws WGBootException

	 */

	public static function boot($bootConfig) {

		

		// Singleton

		if (self::$instance !== null) {

			return false;

		}

		

		// Bootlog

		if (self::$log_boot) {

			self::bootlog('boot start');

		}

		

		// Chemin vers les répertoires

		self::$wgfolder = basename(dirname(dirname(__FILE__)));

		self::$wgbase = realpath(dirname(__FILE__) . '/../') . '/';

		

		// Si $bootConfig est une string, il s'agit du chemin vers un fichier

		if (is_string($bootConfig)) {

			

			// Lecture du fichier de boot

			$read = file_get_contents(self::$wgbase . $bootConfig);

			

			// Lecture impossible

			if (!$read) {

				throw new WGBootException("Invalid boot file: $bootConfig");

			}

			

			// On parcours les lignes du fichier

			foreach ($bootConfig = explode("\n", $read) as $k => $v) {

				

				$v = trim($v);

				

				// Les lignes vides ou de commentaires sont ignorées

				if (empty($v) || substr($v, 0, 1) == '#') {

					unset($bootConfig[$k]);

					continue;

				}

				

			}

		}

		

		// Erreur si $bootConfig n'est pas un tableau valide

		if (!is_array($bootConfig)) {

			throw new WGBootException("Invalid boot config: " . gettype($bootConfig));

		}

		

		// On initialise le conteneur d'application

		$WG = new WG();

			

		// On parcours la séquence de boot

		foreach ($bootConfig as $cmd) {

			

			// On affiche systèmatiquement les erreurs de boot

			error_reporting(E_ALL);

			

			// Bootlog

			if (self::$log_boot) {

				self::bootlog("boot > $cmd");

			}

			

			// Séparation entre commande et arguments

			if (sizeof($cmd = explode(' ', $cmd, 2)) > 1) {

				list($cmd, $args) = $cmd;

			}

			else {

				$cmd = $cmd[0];

				$args = null;

			}

			

			// On test la commande

			switch ($cmd) {



				// INCLUDE php file

				case 'INCLUDE' :

					

					$file = self::$wgbase . $args;

					

					if (!is_file($file)) {

						throw new WGBootException("File not found: $args, in INCLUDE boot instruction");

					}

					else if (!is_readable($file)) {

						throw new WGBootException("File not readable: $args, in INCLUDE boot instruction");

					}

					

					include $file;

					

					break;

					

				

				// LOAD manifest file

				case 'LOAD_MANIFEST' :

					

					$file = self::$wgbase . $args;

						

					if (!is_file($file)) {

						throw new WGBootException("File not found: $args, in LOAD boot instruction");

					}

					else if (!is_readable($file)) {

						throw new WGBootException("File not readable: $args, in LOAD boot instruction");

					}

						

					$data = json_decode(file_get_contents($file), true);

					

					if (!is_array($data)) {

						throw new WGBootException("Invalid JSON file: $args, in LOAD boot instruction");

					}

					

					// On enregistre les données du manifest

					$WG->manifest = $data;

					

					//

					$WG->modules = $data['modules'];

					

					// Initialisation de l'application

					$WG->handleVars();

					$WG->handleHost();

					$WG->handleTimezone();

					$WG->handleDatabase();

					

					break;

					

				// SET

				case 'SET' :



					// Séparation du nom de la variable de sa valeur

					if (sizeof($args = explode(' ', "$args", 2)) > 1) {

						$varname = $args[0];

						$args = $args[1];

					}

					else {

						$varname = $args[0];

						$args = null;

					}

					

					// On compare le nom de la variable

					switch ($varname) {

						case 'log_boot' :

							self::$log_boot = $args == 'On';

							break;

						default :

							throw new WGBootException("Invalid SET variable: $varname");

							break;

					}

					break;

				

				// LOAD module

				case 'LOAD_MODULE' :

					$WG->loadModule($args);

					break;



				// Charger les modules

				case 'AUTOLOAD_MODULES' :

					

					$WG->appCache = isset($WG->vars['enable_app_cache']) && $WG->vars['enable_app_cache']['value'] === true;

					

					if ($WG->appCache) {

					

						$store = ModelManager::get('Store')->getByName('app-cache', 1);

					

						if ($store !== null) {

							// Debug

							if (self::$log_boot) {

								self::bootlog('appcache restore');

							}

							$WG->modules = $store->data['modules'];

							$WG->views   = $store->data['views'];

							$WG->vars    = $store->data['vars'];

							$WG->manifest= $store->data['manifest'];

							break;

						}

					

					}

					

					// Chargement des modules dans le répertoire indiqué

					$WG->handleModules(self::$wgbase . $args);

					

					// Chargement de vues

					// TODO A supprimer, c'est pas super dans le process

					$WG->handleViews();

					

					// Enregistrement du cache d'application

					if ($WG->appCache) {

						

						$store = ModelManager::get('Store')->getByName('app-cache', 1);

						

						// Création du cache s'il n'existe pas

						if (!$store) {

							$store = ModelManager::get('Store')->new

								->set('name', 'app-cache')

								->set('item', 'main');

						}

						

						// Modification du cache

						$store

							->set('update', $_SERVER['REQUEST_TIME'])

							->set('data', array(

								'modules' 	=> $WG->modules,

								'views'  	=> $WG->views,

								'vars'    	=> $WG->vars,

								'manifest'	=> $WG->manifest

							));

						

						// Enregistrement du cache

						$store->save();

						

						// Debug

						if (self::$log_boot) {

							self::bootlog('appcache save');

						}

						

					}

					

					break;

			

				// Commande de debug

				case 'EXIT&PRINT_BOOTLOG' :

					WG::printBootLogs($args == 'verbose=1');

					exit(5);

					break;

					

				// Unknown command

				default :

					throw new WGBootException("Invalid boot instruction: $cmd");

					break;

				

			}

			

			unset($file, $data);

			

		}

		

		// On enregistre l'instance de WG

		self::$instance = $WG;

		

		// Bootlog

		if (self::$log_boot) {

			self::bootlog("boot end");

		}

		

		// La séquence de boot est terminée, on remet le niveau d'erreur normal

		$WG->configureErrorReportingLevel();

		

		return true;

		

	}



	############################################  C O N F I G U R A T I O N



	/**

	 * Charge les variables du manifest principal.

	 * 

	 * @return void

	 */

	protected function handleVars() {

		if (isset($this->manifest['vars'])) {

			// Debug

			if (self::$log_boot) {

				self::bootlog('init > vars (' . sizeof($this->manifest['vars']) . ')');

			}

			$this->set($this->manifest['vars'], 'root', 'root:manifest');

		}

	}



	/**

	 * Détermine le nom d'host de la configuration, et charge ses données.

	 * 

	 * @return void

	 * @throws WGException Si l'host n'est pas déterminable.

	 */

	protected function handleHost() {

		if (!isset($this->manifest['hosts'])) {

			throw new WGException('Your manifest.json must contains a "hosts" section');

		}

		$default = null;

		$host = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';

		// Cherche la config de l'host

		foreach ($this->manifest['hosts'] as $name => $vars) {

			// Les possibilités sont séparées par des tubes |

			$hh = explode('|', $vars['host']);

			// Fetch hosts

			foreach ($hh as $h) {

				if (fnmatch($h, $host)) {

					$this->setHost($name, $vars, $host);

					return;					

				}

			}

			// Default value

			if (isset($vars['default']) && $vars['default'] === true) {

				$default = array($name, $vars);

			}

		}

		if ($default !== null) {

			$this->setHost($default[0], $default[1], $host);

			return;

		}

		throw new WGException("No host configuration for this domain: $host");

	}



	/**

	 * Modifie le nom d'hôte de la configuration, et charge ses variables.

	 * TODO Déplacer la config de l'error reporting ailleurs

	 * 

	 * @param string $name Le nom de la configuration.

	 * @param mixed[] $vars Les variables à charger.

	 * @param string $host Le nom de domaine de l'hôte.

	 * @return void

	 */

	protected function setHost($name, $vars, $host) {

		$this->host = $name;

		$this->set($vars, 'core', 'root:host:' . $name);

		// Debug

		if (self::$log_boot) {

			self::bootlog("set host $name (vars=" . sizeof($vars) . ", realhost=$host)");

		}

		// Enable error reporting

		$this->configureErrorReportingLevel();

		// Fix app url

		$appurl = $this->get('appurl');

		if (strpos($appurl, '{$base.host}') !== false) {

			$this->vars['appurl']['value'] = str_replace('{$base.host}', $host, $appurl);

		}

	}



	/**

	 * Configure la timezome de l'application, grâce à la configuration.

	 * 

	 * @return void

	 */

	protected function handleTimezone() {

		if (isset($this->vars['timezone'])) {

			$tz = $this->vars['timezone']['value'];

			if (self::$log_boot) {

				self::bootlog('set timezone ' . $tz);

			}

			date_default_timezone_set($tz);

			ini_set('date.timezone', $tz);

		}

	}



	/**

	 * Initialise le système de bases de données.

	 * 

	 * @return void

	 */

	protected function handleDatabase() {

		if (self::$log_boot) {

			self::bootlog('init > database ');

		}

		self::lib('Moodel.php');

		$this->db = DatabaseConnectorManager::createDatabase(

			'main',

			'mysql',

			$this->get('db_host'),

			$this->get('db_user'),

			$this->get('db_pwd'),

			$this->get('db_name'),

			$this->get('db_prefix')

		);

	}



	/**

	 * Charge les données des modules.

	 * 

	 * @param string $dir

	 * @throws WGException

	 * @return void

	 */

	protected function handleModules($dir = null) {

		

		// Auto-détermination du chemin

		if (!is_string($dir)) {

			$dir = self::base('modules');

		}

		

		// Log

		if (self::$log_boot) {

			self::bootlog("init modules in $dir");

		}

		

		// Ouverture du répertoire des modules

		if (is_array($files = scandir($dir, 0))) {

			

			// On parcours les fichiers du répertoire

			foreach ($files as $file) {

				

				// On évite les liens symboliques

				if ($file == '.' || $file == '..') continue;

				

				// Préfixer le nom du répertoire par '_' signifie désactiver le module

				if (substr($file, 0, 1) == '_') continue;

				

				// On détermine le chemin vers le fichier de manifest du module

				$path = self::base('modules') . '/' . $file . '/manifest.json';

				

				// Si le fichier n'existe pas, ce module n'est pas chargé comme ça

				if (!is_file($path)) {

					continue;

				}

				

				// Si le fichier n'est pas lisibile, c'est une exception

				if (!is_readable($path)) {

					throw new WGException("Manifest file not readable for module: $file");

				}

				

				// On recupère le contenu du fichier

				$read = file_get_contents($path);

				

				// Fichier vide ou illisible

				if (!$read) {

					throw new WGException("Unable to read manifest file for module: $file");

				}

				

				// On essaye de parser le contenu du fichier

				$read = json_decode($read, true);

				

				// Fichier JSON non valide

				if (!is_array($read)) {

					throw new WGException("Unable to parse manifest file for module: $file");

				}

				

				// Le manifest est valide, on recupère le nom du module (qui n'est pas obligatoirement

				// le nom du répertoire)

				$modname = isset($read['moduleName']) ? $read['moduleName'] : $file;

				

				// Un module existe déjà avec ce nom

				if (array_key_exists($modname, $this->modules)) {

					continue;

				}

				

				// On ajoute la configuration du module dans le manifest

				$this->manifest['modules'][$modname] = $read;

				

				// On ajoute aussi la configuration dans le tableau des modules

				$this->modules[$modname] = $read;

				

				// Si des variables existent, on les charges

				if (isset($read['vars'])) {

					$this->set($read['vars'], $modname, $modname.':manifest');

				}

				

				// Log

				if (self::$log_boot) {

					self::bootlog("init module > $modname");

				}



			}

		}

		else {

			throw new WGException("Unable to open modules directory");

		}

	}



	/**

	 * Charge les données des vues.

	 * 

	 * TODO Ce truc c'est carrément foireux, y'a uniquement les vues en cache...

	 * 

	 * @return void

	 */

	protected function handleViews() {

		// On parcours la liste des modules

		foreach ($this->modules as $modName => $modProp) {

			// Si des vues sont définies pour ce module

			if (isset($modProp['views'])) {

				// Alors on les parcours

				foreach ($modProp['views'] as $viewName => $viewProp) {

					// On ajoute des données dans les propriétés

					$viewProp['name'] = $viewName;

					$viewProp['module'] = $modName;

					// Et on enregistre les vues

					$this->views[$viewName] = $viewProp;

				}

			}

		}

	}

	

	/**

	 * Charge la librairie $file.

	 * Les librairies doivent se trouver dans le répertoire inc.

	 *

	 * @param string $file Le nom du fichier de la librairie.

	 * @return void

	 */

	public static function lib($file) {

		$path = self::$wgbase . "inc/$file";

		if (!is_file($path)) {

			throw new WGException("Library file not found: $file");

		}

		require_once $path;

	}

	

	/**

	 * Renvoyer un chemin par rapport à la base.

	 * 

	 * @param string $path

	 * @return string

	 */

	public static function base($path='') {

		return self::$wgbase . $path;

	}

	

	/**

	 * Renvoyer une URL par rapport à la base.

	 * 

	 * @param string $path

	 * @return string

	 */

	public static function url($path='') {

		if (WG::vars('sslurl') != null) {

			if (self::useSSL()) {

				return self::vars('sslurl') . self::$wgfolder . '/' . $path;

			}

		}

		return self::vars('appurl') . self::$wgfolder . '/' . $path;

	}

	

	/**

	 * Réinitialise le niveau d'erreur_reporting par rapport à la configuration.

	 * Il s'agit de la méthode publique pour réaliser cette action, mais elle ne fonctionne qu'après

	 * le boot de WG. En interne, il faut utiliser configureErrorReportingLevel()

	 * 

	 * Par défaut, et en cas d'erreur, le niveau est mis à 0

	 */

	public static function resetErrorReportingLevel() {

		

			return self::$instance->configureErrorReportingLevel();

		

	}

	

	/**

	 * Réinitialise le niveau d'erreur_reporting par rapport à la configuration.

	 * 

	 * Par défaut, et en cas d'erreur, le niveau est mis à 0

	 */

	protected function configureErrorReportingLevel() {

		

		// Par défaut, le niveau d'erreur est à zero

		$level = 0;



		// Si un niveau d'erreur est défini dans la configuration

		if (isset($this->vars['error_reporting'])) {

				

			// On recupère le niveau

			$level = $this->vars['error_reporting']['value'];

				

			// Si c'est une string, on va comparer avec les constantes

			if (is_string($level)) {

				// Si la constante existe, $level prendra sa valeur

				if (defined($level)) {

					$level = constant($level);

				}

			}

				

			// Si le level est mal défini, on le remet à 0 et on log un warning

			if (!is_int($level)) {

				// TODO Logger un warning

				$level = 0;

			}

				

		}

		

		// On modifie le niveau d'erreur

		error_reporting($level);

		

		// Log de boot

		if (self::$log_boot) {

			self::bootlog("reset error_reporting level=" . error_reporting());

		}

		

	}

	

	############################################ P L U G I N S

	

	/**

	 * Ajouter un plugin à WG.

	*

	* @param Soho_plugin $plugin

	* @throws WGException Si un plugin avec ce nom est déjà enregistré.

	*/

	public static function addPlugin(Soho_Plugin $plugin) {

	

		// Plugin name

		$name = $plugin->getPluginName();

		

		// Bootlog

		if (self::$log_boot) {

			self::bootlog("add plugin > $name (class " . get_class($plugin) . ')');

		}

		

		// Plugin exists

		if (array_key_exists($name, self::$plugins)) {

			throw new WGException("Plugin `$name` allready exists");

		}

	

		// Save plugin

		self::$plugins[$name] = array(

			$plugin,	// L'instance du plugin

			false		// Un flag pour déterminer si le plug est initialisé

		);



	}

	

	/**

	 * Tester si une API est disponible.

	 * 

	 * @param string $apiName

	 * @return boolean

	 */

	public static function apiExists($apiName) {

		foreach (self::$plugins as $plugin) {

			if ($plugin[0]->hasAPI($apiName)) {

				return true;

			}

		}

		return false;

	}

	

	/**

	 * Aliase de apiExists()

	 * 

	 * @param string $apiName

	 * @return boolean

	 */

	public static function hasAPI($apiName) {

		return self::apiExists($apiName);

	}

	

	/**

	 * Renvoi la liste des plugins installés.

	 * 

	 * @return string[]

	 */

	public static function plugins() {

		return array_keys(self::$plugins);

	}

	

	/**

	 * Utiliser une API d'un plugin.

	 *

	 * Cette méthode permet d'obtenir une API spécifique de WG.

	 * Les API sont proposés par les PlUGINS, qui s'intégrent avec addPlugin().

	 * Utilisé simplement, cette méthode renvoi l'API :

	 * 	WG::widgets()		-> renvoi un objet

	 * Utilisé avec des paramètres, et si l'API propose la méthode __handleClass,

	 * cette méthode va répercuter l'appel à l'API :

	 *  WG::widgets('toto')	-> va lancer la méthode __handleClass() sur l'objet de l'api.

	 *

	 * @param string $func

	 * @param array $args

	 * @throws WGException Si l'API n'a pas été trouvé, ou si l'API ne propose pas de __handleCall

	 * @return mixed|unknown

	 */

	public static function __callStatic($func, $args) {

	

		// On parcours les plugins

		foreach (self::$plugins as $pluginName => &$plugin) {

				

			// Le plugin propose l'API demandée

			if ($plugin[0]->hasAPI($func)) {

	

				// Initialiser le plugin si besoin

				if (!$plugin[1]) {

					// Bootlog

					if (self::$log_boot) {

						self::bootlog("init plugin > $pluginName (class " . get_class($plugin[1]) . ')');

					}

					$plugin[0]->init();

					$plugin[1] = true;

				}

				

				// On recupère l'API

				$api = $plugin[0]->getAPI($func);

	

				// On fait un appel à l'API

				if (sizeof($args) > 0) {

						

					// L'API est compatible avec la méthode globale

					if (method_exists($api, '__handleCall')) {

						// Répercution de l'appel sur l'API

						return call_user_func_array(array($api, '__handleCall'), $args);

	

					}

						

					// L'API est compatible avec les méthodes séparées

					if (method_exists($api, '__handle_' . $func)) {

						// Répercution de l'appel sur l'API

						return call_user_func_array(array($api, '__handle_' . $func), $args);

					}

						

					throw new WGException("API $func does not support direct call");

						

				}

	

				// Si l'API propose une méthode __handleGet(), on passe par elle pour renvoyer l'API

				if (method_exists($api, '__handleGet')) {

					return call_user_func_array(array($api, '__handleGet'), array($func));

				}

	

				// Si ce n'est pas un appel, on renvoi l'API

				return $api;

	

			}

		}

	

		// L'API n'a pas été trouvé

		throw new WGException("WG API not found: $func");

	

	}

	

	############################################  D A T A B A S E

	

	/**

	 * Renvoi le connecteur à la base de donnée courante.

	 *

	 * @return MySQLDatabaseConnector

	 */

	public static function database() {

		return self::$instance->db;

	}

	

	/**

	 * Tester si le model $modelName est chargé et prêt à être

	 * utilisé. Si le model n'est pas chargé et que $autoload

	 * vaut true, le model est chargé automatiquement.

	 *

	 * Pour des raisons de dépendances, tout le module contenant

	 * le modèle est chargé.

	 *

	 * @param string $modelName Le nom du model à charger.

	 * @param boolean $autoload Si vaut true, le model sera chargé s'il ne l'est pas déjà.

	 * @return boolean Renvoi true si le model est chargé.

	 */

	public static function model($modelName, $autoload=true) {

		// Fetch modules

		foreach (self::$instance->modules as $modName => $modProp) {

			if (isset($modProp['models'][$modelName])) {

				if (!self::module($modName, false)) {

					if ($autoload) {

						return self::module($modName, true);

					}

					else {

						return false;

					}

				}

				return true;

			}

		}

		return false;

	}

	

	############################################  M O D U L E S

	

	/**

	 * Renvoi le tableau de configuration des modules.

	 * 

	 * @return mixed[]

	 */

	public static function modules() {

		return self::$instance->modules;

	}

	

	/**

	 * Indique si un module est chargé.

	 * Permet aussi de charger un module non chargé.

	 *

	 * @param string $moduleName Nom du module.

	 * @param boolean $autoload Indique si le module non existant doit être chargé.

	 * @return boolean

	 */

	public static function module($moduleName, $autoload=true) {

		if (in_array($moduleName, self::$instance->loaded)) {

			return true;

		}

		if (isset(self::$instance->modules[$moduleName])) {

			if ($autoload) {

				self::$instance->loadModule($moduleName);

				return true;

			}

		}

		return false;

	}

	

	/**

	 * Charge un module.

	 * 

	 * @param string $moduleName Nom du module à charger.

	 * @param string $parentModule Nom du module parent, dépendant de ce module.

	 * @return void

	 * @throws WGException Si le module n'est pas trouvable.

	 */

	protected function loadModule($moduleName, $parentModule=null) {

		if (in_array($moduleName, $this->loaded)) {

			return;

		}

		if (isset($this->modules[$moduleName])) {

			if (self::$log_boot) {

				self::bootlog("load module $moduleName" . ($parentModule != null ? " (required by $parentModule)" : ''));

			}

			$module = $this->modules[$moduleName];

			// Save vars

			if (isset($module['vars'])) {

				$this->set($module['vars'], $moduleName, $moduleName . ':manifest');

			}

			// Load dependent modules

			if (isset($module['depends'])) {

				foreach ($module['depends'] as $subModule) {

					$this->loadModule($subModule, $moduleName);

				}

			}

			// Load models

			if (isset($module['models'])) {

				foreach ($module['models'] as $m => $f) {

					// Log

					if (self::$log_boot) {

						self::bootlog("load model > $m (defined by $moduleName)");

					}

					$script = self::base() . $f;

					require_once $script;

				}

			}

			// Save

			$this->loaded[] = $moduleName;

		}

		else {

			throw new WGException("module $moduleName not found".($parentModule !== null ? ", required by module $parentModule" : ''));

		}

	}

	

	############################################  V A R I A B L E S

	

	/**

	 * @param mixed[] $values

	 * @param string $namespace 

	 * @param string $provider

	 */

	private function set($values, $namespace, $provider) {

		

		foreach ($values as $key => $value) {



			// On s'assure que le format d'entrée soit valide (un tableau)

			if (!is_array($value) || !isset($value['value'])) {

				$value = array('value' => $value);

			}

			

			// Remplacement automatique pour les strings

			if (is_string($value['value'])) {

				$value['value'] = str_replace('{$base.path}', self::$wgbase, $value['value']);

			}

			

			// La variable existe déjà

			if (array_key_exists($key, $this->vars)) {

				// On vérifie que la variable est bien overridable

				

				if (

						// Soit il s'agit du même module

						in_array($provider, $this->vars[$key]['src']) ||

						// Soit la variable l'autorise 

						isset($this->vars[$key]['overridable']) && $this->vars[$key]['overridable'] === true

				) {

					// On ajoute un provider

					$this->vars[$key]['src'][] = $provider;

					// Et on modifie la valeur de la variable

					$this->vars[$key]['value'] = $value['value'];

					// On passe à la variable suivante

					continue;

				}

				// Sinon c'est une erreur de sécurité

				else {

					throw new WGSecurityException("Variable `$key` is not overridable by `$provider`, allready defined by `{$this->vars[$key]['src'][0]}`");

				}

			}

			

			// La variable est nouvelle, on va écrire les données meta

			else {

				// L'espace de nom

				$value['ns'] = $namespace;

				// Et le fournisseur

				$value['src'] = array($provider);

				// Le status de remplacement

				$value['overridable'] = (isset($value['overridable']) && $value['overridable'] === true);

				// Et on modifie la valeur en laissant continuer

			}

			

			// On enregistre la variable

			$this->vars[$key] = $value;

			

		}



	}



	/**

	 * @return mixed|null

	 */

	protected function get($key) {

		if (!array_key_exists($key, $this->vars)) {

			return null;

		}

		return $this->vars[$key]['value'];

	}



	/**

	 * @return 

	 */

	public static function vars($key=null) {

		

		// Getter

		if ($key !== null) {

			// Variable doesn't exists

			if (!array_key_exists($key, self::$instance->vars)) {

				return null;

			}

			// Return result

			return self::$instance->vars[$key]['value'];

		}

		

		// Getter global

		

		throw new WGException("Not implemented yet");

		

		

		/*if ($key !== null) {

			$var = self::$instance->get($key);

			// Array handler

			if (is_array($var)) {

				// Subkey

				if ($subkey !== null) {

					$var = $var[$subkey];

				}

				// Nested value

				else if (isset($var['value'])) {

					$var = $var['value'];

				}

			}

			// Tokens replacement

			if (is_string($var)) {

				// TODO Ajouter base.url et current.url

				$var = str_replace(

					'{$base.path}',

					self::$wgbase,

					$var

				);

			}

			return $var;

		}

		else {

			return self::$instance->vars;

		}*/

	}

	

	/**

	 * TODO Virer les mdp

	 */

	public static function vars_raw() {

		return self::$instance->vars;

	}

	

	############################################  S E C U R I T Y





	/**

	 * Charge le système de sécurité de WG. L'initialisation du système

	 * se fait dans le fichier 'security.php' qui se trouve dans le wgfolder,

	 * ce qui permet d'implémenter toutes sortes de processus et de relier

	 * l'authentification sur WG avec un autre CMS.

	 * 

	 * Le système de sécurité n'est pas sensé renvoyer d'exceptions, ni de

	 * bloquer l'execution du script : il se contente de charger la session,

	 * de l'inscrire dans WG (avec WG::session()) et de rendre la main.

	 * La vérification des droits doit se faire après, en fonction de la

	 * requête du client.

	 * 

	 * @throw WGSecurityException

	 */

	public static function security() {

		

		// On charge le fichier de police de sécurité, c'est à lui de fournir

		// une session valide avec WG::session()

		include self::base('security.php');

		

		// Dans le cas ou une session a bien été enregistrée

		if (self::$instance->session != null) {

			// On regarde si un utilisateur est loggé

			if (self::$instance->session->isLogged()) {

				// Si oui, on met à jour les données de l'user

				// TODO Ne logger que pour les auth?

				self::$instance->session->getRealUser()

					->set('last_connection', time())

					->set('last_medium', PHP_SAPI)

					->save();

			}

		}



	}



	/**

	 * Renvoi l'utilisateur courant, en utilisant la session. Si la session n'est pas inscrite, alors

	 * cette méthode renvoi NULL dans tous les cas de figures.

	 * 

	 * Cette méthode utilise la méthode WGCRT_Session::getUser() pour récupérer l'utilisateur,

	 * ce qui signifie qu'elle est influencée par le sudo et qu'elle renvoi toujours l'utilisateur

	 * utilisé. 

	 * 

	 * @return TeamMember|null

	 */

	public static function user() {

		return self::$instance->session != null ? self::$instance->session->getUser() : null;

	}

	

	/**

	 * Modifie ou récupére la session actuelle. Il n'est pas possible de nuller la session,

	 * car si $session n'est pas fourni cette méthode fera office de getter. 

	 * 

	 * @params WGCRT_Session|null $session

	 * @return WGCRT_Session|null

	 */

	public static function session(WGCRT_Session $session=null) {

		if ($session !== null) {

			self::$instance->session = $session;

		}

		return self::$instance->session;

	}

	

	/**

	 * Inscrit l'utilisateur dans la session.

	 * 

	 * Cette méthode est dépréciée car WG doit utiliser WGCRT_Session, et non pas agir

	 * dessus. Ce n'est pas au script de logger l'utilisateur, c'est à la session

	 * de déterminer l'utilisateur courant ou de gêrer le auth d'un client.

	 * Si pour des raisons spécifiques vous souhaitez logger un utilisateur quand même,

	 * utilisez WGCRT_Session:login().

	 *

	 * @param Moodel<TeamMember> $model L'instance du model du compte utilisateur.

	 * @return void

	 * @deprecated

	 */

	public static function login(Moodel $model) {

		trigger_error("Method WG::login() is deprecated", E_USER_DEPRECATED);

		if (self::$instance->session) {

			self::$instance->session->login($model);

		}

	}

	

	/**

	 * Termine la session actuelle.

	 *

	 * @return void

	 */

	public static function logout() {

		self::$instance->session->logout();

	}

	

	/**

	 * Tester si l'utilisateur $user possède les flags $flags.

	 *

	 * Cette méthode permet de sécuriser une connexion en vérifiant que l'utilisateur

	 * $user possède bien les flags $flags. Si $user n'est pas défini, l'utilisateur

	 * actuellement loggé sur la session courrante sera utilisé.

	 * La variable $quit permet de spécifier si l'exécution du script doit être interrompu

	 * si le test n'est pas validé.

	 *

	 * @param string $flags Les flags à vérifier.

	 * @param boolean $quit Si vaut true, l'exécution du script sera interrompu si le test n'est pas validé.

	 * @param null|Moodel<TeamMember> $user L'utilisateur à tester. Si ce paramètre n'est pas fourni,

	 *  l'utilisateur courant est utilisé.

	 * @return boolean

	 * @throws WGInvalidArgumentException Si l'argument $flags n'est pas une string.

	 */

	public static function checkFlags($flags, $quit = false, $user = null) {

		// En cas d'erreur de programmation, il faut absolument couper le processus

		if (!is_string($flags)) {

			throw new WGInvalidArgumentException("Invalid flags to check");

		}

		// Get current user if $user is not defined

		if ($user === null) {

			$user = WG::user();

		}

		if (!$user) {

			if ($quit) {

				self::quit('Not logged');

			}

			return false;

		}

		// Get user's flags

		$ref = $user->get('flags');

		// Root immunity

		if (strpos($ref, 'Z') !== false) {

			return true;

		}

		// Handle multiple-flags

		if (strlen($flags) > 1) {

			$r = true;

			for ($i = 0, $j = strlen($flags); $i < $j; $i++) {

				$r = $r && self::checkFlags($flags{$i}, $quit, $user);

			}

			if ($r) {

				return true;

			}

		}

		// Check flag with user's account

		else if (strpos($ref, $flags) !== false) {

			return true;

		}

		// Test failure

		if ($quit) {

			self::quit('invalid user level');

		}

		return false;

	}

	

	/**

	 * Tester si un utilisateur possède les groupes donnés.

	 * 

	 * @param string $groups Une liste de noms de groupes, séparés par des espaces.

	 * @param boolean $quit Indique si l'execution doit être stoppée si la vérification des flags renvoi

	 * 	une réponse négative. Par défaut : FALSE.

	 * @param Moodel<TeamMember>|null $user L'utilisateur à tester. Par défaut, la valeur NULL indique

	 *  à la méthode de déterminer automatiquement l'utilisateur en utilisant WG::user().

	 * @return boolean

	 * @throws WGInvalidArgumentException Si l'argument $groups n'est pas une string.

	 */

	public static function checkGroups($groups, $quit = false, $user = null) {

		// En cas d'erreur de programmation, il faut absolument couper le processus

		if (!is_string($groups)) {

			throw new WGInvalidArgumentException("Invalid groups to check");

		}

		// Get current user if $user is not defined

		if ($user === null) {

			$user = WG::user();

		}

		if (!$user) {

			if ($quit) {

				self::quit('Not logged');

			}

			return false;

		}

		// Root immunity

		if (strpos($user->get('flags'), 'Z') !== false) {

			return true;

		}

		// Fetch groups

		foreach (explode(' ', $groups) as $group) {

			if (!WG::user()->hasGroup($group)) {

				return false;

			}

		}

		return true;

	}



	/**

	 * Generer une paire de clé asymétrique pour RSA, enregistre les clés publiques et privées

	 * en session, et renvoi la clé publique.

	 * 

	 * C'est la première étape du processus de dialogue avec RSA+AES : dans un premier temps,

	 * le client demande une clé publique de cryptage au serveur pour pouvoir dialoguer avec lui.

	 * 

	 * @return mixed[]

	 */

	public static function generateKeypair() {

		WG::lib('jcryption/jcryption.php');

		// Load keys

		require WG::base('inc/jcryption/100_1024_keys.inc.php');

		// Create jCryption

		$keyLength = 1024;

		$jCryption = new jCryption();

		$keys = $arrKeys[mt_rand(0, 100)];

		// Create keypairs

		$_SESSION['jcryption']['e'] = array(

				'int' => $keys['e'],

				'hex' => $jCryption->dec2string($keys['e'], 16)

		);

		$_SESSION['jcryption']['d'] = array(

				'int' => $keys['d'],

				'hex' => $jCryption->dec2string($keys['d'], 16)

		);

		$_SESSION['jcryption']['n'] = array(

				'int' => $keys['n'],

				'hex' => $jCryption->dec2string($keys['n'], 16)

		);

		// Return keypairs data

		return array(

				'e' => $_SESSION['jcryption']['e']['hex'],

				'n' => $_SESSION['jcryption']['n']['hex'],

				'maxdigits' => intval($keyLength * 2 / 16 + 3)

		);

	}

	

	/**

	 * Tente de valider un dialogue crypté avec jCryption. Cette méthode renvoi NULL si l'étape

	 * generateKeypair n'a pas été faite, ou si la session ne contient plus les données qu'il faudrait.

	 * 

	 * C'est la deuxième étape du processus de dialogue avec RSA+AES : le client a envoyé sa clé

	 * de cryptage AES au serveur, en la codant avec la clé publique du serveur. Le serveur peut

	 * donc essayer de décoder la clé. Pour tester si le client et le serveur disposent de la même

	 * clé de cryptage, le serveur renvoi un 'challenge' : il crypte la clé du client avec cette même

	 * clé, et renvoi le résultat au client. Le client peut décoder ce message, et vérifier si la

	 * clé que le serveur lui a renvoyé est bien la bonne. Si cette opération réussie, alors le dialogue

	 * est validé.

	 * 

	 * @param string $key La clé

	 */

	public static function handshake($key) {

		if (!isset($_SESSION['jcryption']['e'])) {

			return null;

		}

		// Create jCryption

		WG::lib('jcryption/jcryption.php');

		$keyLength = 1024;

		$jCryption = new jCryption();

		// Create key

		$nkey = $jCryption->decrypt(

				$key,

				$_SESSION['jcryption']['d']['int'],

				$_SESSION['jcryption']['n']['int']

		);

		// Clear

		unset($_SESSION['jcryption']['e']);

		unset($_SESSION['jcryption']['d']);

		unset($_SESSION['jcryption']['n']);

		// Store key

		$_SESSION['jcryption']['key'] = $nkey;

		// Return challenge

		return array('challenge' => AesCtr::encrypt($nkey, $nkey, 256));

	}



	/**

	 * Crypter des données en AES.

	 * 

	 * @param Serializable|string[]|string $data Les données à encoder.

	 * @param string|null $key La clé d'encodage, ou NULL pour utiliser la clé de dialogue

	 *  entre le client et le serveur (réquiert un generateKeypair et un handshake).

	 * @param boolean $cryptKeys Au cas où $data serait un tableau, indique si les

	 *  clés du tableau doivent être cryptées.

	 * @return string|string[]

	 */

	public static function aesEncrypt($data, $key = null, $cryptKeys = false) {

		// Auto-détermination de la clé

		if ($key === null) {

			if (!isset($_SESSION['jcryption']['key'])) {

				return null;

			}

			$key = $_SESSION['jcryption']['key'];

		}

		// Sérialisation

		// TODO En JSON plutôt non ?

		if (is_object($data)) {

			$data = serialize($data);

		}

		// Tableau

		else if (is_array($data)) {

			foreach ($data as $k => $v) {

				if ($cryptKeys) {

					// TODO C'est pas du genre à faire foirer le foreach ça ? (un add + un delete)

					$data[self::aesEncrypt($k, $key, 256)] = self::aesEncrypt($v, $key, 256);

					unset($data[$k]);

				}

				else {

					$data[$k] = self::aesEncrypt($v, $key, 256);

				}

			}

			return $data;

		}

		return AesCtr::encrypt("$data", $key, 256);

	}

	

	/**

	 * Décrypter des données en AES.

	 *

	 * @param Serializable|string[]|string $data Les données à décoder.

	 * @param string|null $key La clé de décodage, ou NULL pour utiliser la clé de dialogue

	 *  entre le client et le serveur (réquiert un generateKeypair et un handshake).

	 * @param boolean $cryptKeys Au cas où $data serait un tableau, indique si les

	 *  clés du tableau doivent être décryptées.

	 * @return string|string[]

	 */

	public static function aesDecrypt($data, $key = null, $cryptKeys = false) {

		if ($key === null) {

			if (!isset($_SESSION['jcryption']['key'])) {

				return null;

			}

			$key = $_SESSION['jcryption']['key'];

		}

		if (is_object($data)) {

			$data = serialize($data);

		}

		else if (is_array($data)) {

			foreach ($data as $k => $v) {

				if ($cryptKeys) {

					$data[self::aesEncrypt($k, $key, 256)] = self::aesDecrypt($v, $key, 256);

					unset($data[$k]);

				}

				else {

					$data[$k] = self::aesDecrypt($v, $key, 256);

				}

			}

			return $data;

		}

		return AesCtr::decrypt("$data", $key, 256);

	}

	

	/**

	 * Renvoi TRUE si le système de cryptage RSA+AES a été initialisé.

	 * 

	 * @return boolean

	 */

	public static function useAES() {

		return isset($_SESSION['jcryption']['key']);

	}

	

	/**

	 * Renvoi TRUE si la session est actuellement en SSL.

	 * 

	 * @return boolean

	 */

	public static function useSSL() {

		return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on';

	}

	

	/**

	 * Renvoi un tableau contenant les données des digest files.

	 * 

	 * TODO Mise en cache

	 *

	 * @return mixed[]

	 */

	public static function htdigests() {

		$r = array();

		foreach (self::$instance->modules as $modName => $module) {

			if (isset($module['htdigests'])) {

				foreach ($module['htdigests'] as $realm => $info) {

					$info['module'] = $modName;

					$info['realm'] = $realm;

					$r[$realm] = $info;

				}

			}

		}

		return $r;

	}

	

	/**

	 * Recupérer des infos sur un fichier de digest.

	 * 

	 * @param string $name Nom du fichier de digest.

	 * @param boolean $readEntries Indique si les données du fichier digest doivent

	 *  être renvoyées, ou bien si on souhaite avoir les infos du digest.

	 * @return mixed[]

	 */

	public static function htdigest($name, $readEntries = false) {

	

		// On recupère la liste des htdigest

		$files = self::htdigests();

	

		// Le htdigest n'existe pas

		if (!isset($files[$name])) {

			return null;

		}

	

		// On renvoi les infos du htdigest

		if (!$readEntries) {

			return $files[$name];

		}

	

		// Lecture du fichier htdigest

		$fg = @file_get_contents(WG::base($files[$name]['file']));

		if (!$fg) {

			return array();

		}

	

		// Tableau de sortie

		$r = array();

	

		// On parcours les lignes du fichier

		foreach (explode("\n", $fg) as $entry) {

			$entry = trim($entry);

			// Si la ligne est vide ou s'il s'agit d'un commentaire, on passe

			if (empty($entry) || $entry{0} == '#') continue;

			// On recupère les données

			list($user, $realm, $hash) = explode(':', $entry, 3);

			// Et on les enregistrent dans le tableau de sortie

			$r[] = array('user' => $user, 'realm' => $realm, 'hash' => $hash);

		}

	

		return $r;

	}

	

	/**

	 * Exécute le processus d'authentification HTTP Basic Realm.

	 *

	 * Ce processus d'authentification utilise les entêtes HTTP pour

	 * dialoguer avec le client, et demander les identifiants de connexion.

	 * Cette solution est simple et rapide, mais elle n'est pas très sécurisée.

	 *

	 * @return true|void

	 * @link http://tools.ietf.org/html/rfc2617

	 */

	public static function basicRealmAuthentication() {

		// Patch (with htaccess)

		$matches = array();

		if ((!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW']))

				&& preg_match('/Basic\s+(.*)$/i', $_SERVER['REMOTE_USER'], $matches) > 0) {

			list($name, $pass) = explode(':', base64_decode($matches[1]));

			$_SERVER['PHP_AUTH_USER'] = strip_tags($name);

			$_SERVER['PHP_AUTH_PW'] = strip_tags($pass);

		}

		// Authentication

		if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']) && !isset($_SESSION['WG_AUTH_LOGOUT'])) {

			$user = ModelManager::get('TeamMember')->get(array(

					'login' => $_SERVER['PHP_AUTH_USER'],

					'password' => sha1($_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW'])

			));

			if (sizeof($user) === 1) {

				self::login($user[0]);

				return true;

			}

		}

		// Logout

		unset($_SESSION['WG_AUTH_LOGOUT']);

		// Unauthorized

		header('WWW-Authenticate: Basic realm="Private area"', true);

		header('HTTP/1.0 401 Unauthorized', true);

		exit("401 Unauthorized");

	}

	

	/**

	 * Envoi un message d'erreur vers la sortie standard, en fonction du type

	 * de contenu (Content-Type) du mode actuel de transaction avec le client.

	 * Cette méthode supporte les types de contenu suivants :

	 * - text/plain

	 * - text/html

	 * - text/xml

	 * - image/png

	 * - application/json (par défaut)

	 *

	 * @param string $error Le message d'erreur.

	 * @param int $errorCode Le code HTTP du status d'erreur.

	 * @param string $contentType Le type de contenu.

	 * @return void

	 */

	public static function formatError($error, $errorCode, $contentType) {

		// Send headers

		header('HTTP/1.0 ' . $errorCode . ' ' . $error, true, $errorCode);

		if (is_string($contentType)) {

			header('Content-type: ' . $contentType, true);

		}

		else {

			$contentType = 'text/plain';

		}

		// Output according to content type

		switch ($contentType) {

			

			case 'text/plain' :

				echo "ERROR $errorCode : $error";

				return;

				

			case 'text/html' :

				echo '<h1>Error '.$errorCode.'</h1><p>'.htmlspecialchars($error).'</p>';

				return;

				

			case 'text/xml' :

				echo '<?xml version="1.0" encoding="utf-8" ?><rsp stat="error" errorcode="'.$errorCode.'"><![CDATA['.utf8_encode(htmlspecialchars($error)).']]></rsp>';

				return;

				

			case 'image/png' :

				header('HTTP/1.0 200 OK', true);

				$w = imagefontwidth(2) * strlen($error) + 2;

				$im = imagecreatetruecolor($w, 30);

				imagefilledrectangle($im, 0, 0, $w, 30, imagecolorallocate($im, 255, 255, 255));

				imagestring($im, 3, 2, 1, 'Error ' . $errorCode, imagecolorallocate($im, 255, 0, 0));

				imagestring($im, 2, 2, 15, $error, imagecolorallocate($im, 0, 0, 0));

				imagepng($im);

				return;

				

			case 'text/css' :

				echo 'body{background:red !important}body:before{content:"[' . $errorCode . '] ' . $error . '";font-size:2em}'; 

				return;

				

			case 'application/json' :

				echo '{"error":"'.htmlspecialchars($error).'","errorcode":"'.$errorCode.'"}';

				return;

				

			default :

				return;

				

		}

	}

	

	/**

	 * Renvoi les donnés de configuration des flags.

	 *

	 * TODO Mise en cache

	 * 

	 * @return mixed[]

	 */

	public static function flags() {

		$r = array();

		// Fetch modules

		foreach (self::$instance->modules as $modName => $modProp) {

			// Widget found

			if (isset($modProp['flags'])) {

				foreach ($modProp['flags'] as $flag) {

					$flag['module'] = $modName;

					$flag['level'] = isset($flag['level']) ? intval($flag['level']) : WG::$defaultFlagLevel;

					$r[$flag['flag']] = $flag;

				}

			}

		}

		return $r;

	}

	

	/**

	 * Renvoi l'instance de l'utilisateur système.

	 * Cette commande sert aussi à déterminer cet utilisateur (la première fois).

	 * 

	 * @return Moodel<TeamMember>

	 */

	public static function sysuser() {

		if (self::$sysuser === null) {

			$user = ModelManager::get('TeamMember')->get(array('login' => WG::vars('mimo_account_username')));

			if (sizeof($user) > 0) {

				self::$sysuser = $user[0];

			}

		}

		return self::$sysuser;

	}

	

	############################################  S T A T I C   G E T T E R S

	

	/**

	 * Renvoi le nom d'hôte de la configuration.

	 * 

	 * @return string

	 */

	public static function host() {

		return self::$instance->host;

	}



	/**

	 * Renvoi un tableau contenant toutes les infos des differents manifests.

	 *

	 * @param string $name Nom du type de donnée manifest à renvoyer, ou null pour obtenir tout le manifest.

	 * @return mixed[]

	 */

	public static function manifest($name = null) {

		

		if (is_string($name)) {

			$r = array();

			foreach (self::$instance->manifest['modules'] as $module => $manifest) {

				if (isset($manifest[$name])) {

					if (is_array($manifest[$name])) {

						$r = array_merge($r, $manifest[$name]);

					}

					else {

						$r[] = $manifest[$name];

					}

				}

			}

			return $r;

		}

		

		return self::$instance->manifest;

	}

	

	############################################  S T O R E S

	

	/**

	 * Renvoi un tableau contenant tous les paramètres des stores.

	 *

	 * TODO Mise en cache

	 *

	 * @return mixed[]

	 */

	public static function stores() {

		$r = array();

		// Fetch modules

		foreach (self::$instance->modules as $modName => $modProp) {

			if (isset($modProp['stores'])) {

				foreach ($modProp['stores'] as $store) {

					$store['module'] = $modName;

					$r[] = $store;

				}

			}

		}

		return $r;

	}

	

	/**

	 * Renvoi les données d'un store.

	 * 

	 * @param string $name Nom du store.

	 * @param string $item Type de donnée.

	 * @param int $length La taille du tableau de sortie. Si la taille vaut 1, alors

	 *  la méthode renvoi 

	 * @return Moodel<Store>[] Si length est > 1 ou à 0.

	 * @return Moodel<Store>|null Si length vaut 1.  

	 */

	public static function store($name, $item = 'main', $length = 1) {

		$tmp = ModelManager::get('Store')->get(array('name' => $name, 'item' => $item));

		if ($length === 1) {

			return sizeof($tmp) > 0 ? $tmp[0] : null;

		}

		return array_slice(

				$tmp,

				0,

				$length

		);

	}

	

	/**

	 * Fabrique un nouveau store. Cette méthode renvoi une instance de model

	 * qui n'a pas encore été enregistrée en base.

	 * 

	 * @param string $name Nom du store.

	 * @param string $item Type de donnée.

	 * @return Moodel<Store>

	 */

	public static function newStore($name, $item = 'main') {

		return ModelManager::get('Store')

			->new

			->set('name', $name)

			->set('item', $item)

			->set('update', time());

	}

	

	############################################  V I E W S

	

	/**

	 * Affiche la vue identifiée par le nom $viewName.

	 *

	 * Cette méthode va premièrement chercher la vue dans les différents modules

	 * installés. Si la vue existe, le module associé sera chargé (s'il ne l'est

	 * pas déjà). Le processus de verification des flags est lancé avant le lancement

	 * de la vue.

	 *

	 * Le contenu de la vue est directement envoyée à la sortie standard.

	 *

	 * @param string $viewName Le nom identifiant de la vue.

	 * @return string

	 * @throw WGSecurityException Si la session n'est pas loggée.

	 * @throw WGException Si la vue n'existe pas.

	 */

	public static function view($viewName) {

		if (!is_string($viewName)) {

			throw new View404Exception('no view name given');

		}

		// Fetch modules

		foreach (self::$instance->modules as $modName => $modProp) {

			// Search the right view

			if (isset($modProp['views'][$viewName])) {

				// View found

				$view = $modProp['views'][$viewName];

				// Security

				if (isset($view['requireFlags'])) {

					WG::checkFlags($view['requireFlags'], true);

				}

				// Load models

				self::$instance->loadModule($modName);



				// Create page

				$page = new MiniPage();

				$page->title = self::vars('appName'); // deprecated

				$page->view = $viewName;

				$page->resources = 'wg/modules/'.$modName.'/public/';

				$page->core = 'wg/modules/core/public/';

				$GLOBALS['_PAGE'] = $page;

				// AES Decrypt



				// Call view

				ob_start();

				require WG::base($view['script']);

				$contents = ob_get_contents();

				ob_end_clean();

				// AES Encrypt

				if (isset($_SESSION['jcryption']['key'])) {

					// Load AES library

					WG::lib('jcryption/jcryption.php');

					// Encrypt data

					$contents = WG::aesEncrypt($contents, $_SESSION['jcryption']['key']);

				}

				else {

					header('Content-Type: text/html; charset=UTF-8');

				}

				// Return data

				return $contents;

			}

		}

		throw new View404Exception("view not found: $viewName");

	}

	

	/**

	 * Renvoi un tableau contenant tous les paramètres des vues.

	 *

	 * @return mixed[]

	 */

	public static function views() {

		return self::$instance->views;

	}

	

	############################################  W E B S E R V I C E S

	

	/**

	 * Renvoi un tableau contenant tous les paramètres des webservices.

	 *

	 * TODO Mise en cache

	 *

	 * @return array

	 */

	public static function webservices() {

		$r = array();

		// Fetch modules

		foreach (self::$instance->modules as $modName => $modProp) {

			if (isset($modProp['webservices'])) {

				foreach ($modProp['webservices'] as $wsName => $ws) {

					$ws['name'] = $wsName;

					$ws['module'] = $modName;

					$r[] = $ws;

				}

			}

		}

		return $r;

	}



	/**

	 * Execute le WebService identifié par le nom $name.

	 * Cette méthode renvoi true si le service a été trouvé et que son

	 * execution c'est déroulée sans erreur.

	 *

	 * Cette méthode envoi automatiquement le résultat de l'execution

	 * ou bien les erreurs vers la sortie standard.

	 * Une entête Content-type est aussi envoyée dès le début du lancement

	 * du service.

	 * Si le dev_mode n'est pas activé, le niveau d'error_reporting sera

	 * porté à zéro (aucune erreur) pour ne pas compromettre le type

	 * de contenu.

	 *

	 * @param string $name Le nom du WebService à executer.

	 * @return boolean

	 */

	public static function executeWebservice($name, $handleAES = true) {

		if (!is_string($name)) {

			self::formatError('Bad Service Name', 404, 'application/json');

			return false;

		}

		// AES

		if ($handleAES && isset($_SESSION['jcryption']['key'])) {

			// Load AES library

			WG::lib('jcryption/jcryption.php');

			// Get shared AES key

			$key = $_SESSION['jcryption']['key'];

			// Decrypt name

			$name = AesCtr::decrypt($name, $key, 256);

		}

		if (WG::vars('dev_mode') !== true) {

			error_reporting(0);

		}

		// Fetch modules

		foreach (self::$instance->modules as $modName => $modProp) {

			// Webservice found

			if (isset($modProp['webservices'][$name])) {

				$ws = $modProp['webservices'][$name];

				// Content type

				if (!isset($ws['returnType'])) {

					$ws['returnType'] = null;

				}

				else {

					header('Content-type: ' . $ws['returnType']);

				}

				// Force SSL

				if (isset($ws['sslOnly']) && $ws['sslOnly'] === true) {

					if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on') {

						self::formatError('SSL/TLS Required', 401, $ws['returnType']);

						header('Redirect: ' . WG::vars('sslurl') . substr($_SERVER['REQUEST_URI'], 1));

						return false;

					}

				}

				// Security

				if (!isset($ws['disableSecurity']) || $ws['disableSecurity'] !== true) {

					try {

						WG::security();

						// Flags

						if (isset($ws['requireFlags'])) {

							if (!WG::checkFlags($ws['requireFlags'])) {

								throw new WGSecurityException('require flag');

							}

						}

					}

					catch (Exception $ex) {

						self::formatError('Unauthorized', 401, $ws['returnType']);

						wgcrt_log_exception($ex);

						return false;

					}

				}

				// Hosts

				if (isset($ws['requireHost'])) {

					if (!fnmatch($ws['requireHost'], $_SERVER['REMOTE_ADDR'])) {

						self::formatError('Unauthorized host', 401, $ws['returnType']);

						return false;

					}

				}

				// Disabled

				if (isset($ws['disabled']) && $ws['disabled'] === true) {

					self::formatError('Service Unavailable', 503, $ws['returnType']);

					return false;

				}

				// Method

				if (isset($ws['method'])) {

					$m = strtoupper($ws['method']);

					if ($m != 'BOTH' && $m != $_SERVER['REQUEST_METHOD']) {

						self::formatError('Method Not Allowed', 405, $ws['returnType']);

						return false;

					}

				}

				// Ok, we can start the webservice

				try {

					include self::base($ws['script']);

					return true;

				} catch (WGSecurityException $ex) {

					self::formatError('Unauthorized', 401, $ws['returnType']);

					wgcrt_log_exception($ex);

				} catch (Exception $ex) {

					self::formatError('Internal Server Error ('.(WG::vars('dev_mode') === true ? get_class($ex) . ' - ' . $ex->getMessage() : get_class($ex)).')', 500, $ws['returnType']);

					wgcrt_log_exception($ex);

				}

				return false;

			}

		}

		// Webservice not found

		// Default webservice data type is JSON

		self::formatError('Webservice Not found', 404, 'application/json');

		//self::formatError('Webservice Not found: ' . $name . (isset($_SESSION['jcryption']['key']) ? ' (AES)' : ''), 404, 'application/json');

		return false;

	}



	/**

	 * Renvoi un tableau contenant tous les paramètres des lives.

	 *

	 * TODO Mise en cache

	 *

	 * @return array

	 */

	public static function lives() {

		$r = array();

		// Fetch modules

		foreach (self::$instance->modules as $modName => $modProp) {

			if (isset($modProp['live'])) {

				foreach ($modProp['live'] as $live) {

					$live['module'] = $modName;

					$r[] = $live;

				}

			}

		}

		return $r;

	}



	############################################ U S E R    I N T E R F A C E



	/**

	 * Renvoyer le code HTML du coeur d'application WG.js au client.

	 *

	 * Cette fonction va :

	 * 	1) Récupérer l'application javascript (WG.js)

	 *  2) Modifier le code javascript pour le configurer

	 *  3) Intégrer ce code javascript dans le template HTML (template.code.html)

	 *  4) Renvoyer le tout vers la sortie standard

	 * 

	 * @return void

	 */

	public static function core() {

		header('Content-type: text/html');

		/*$javascript = file_get_contents(WG::base('modules/core/WG.js'));

		$user = WG::user();

		$url = WG::vars('appurl');

		if (WG::vars('sslurl') != null && WG::useSSL()) {

			$url = WG::vars('sslurl');

		}

		$javascript = str_replace(array(

				'wg_appName:"SoHo"',

				'wg_appVersion:"3.0"',

				'wg_url:"http://soho/"',

				'wg_lastUpdate:0',

				'wg_updateDelay:30000',

				'wg_sessionAge:0',

				'wg_logged:false'

		), array(

				'wg_appName:"'.addcslashes(WG::vars("appName"), '"\\').'"',

				'wg_appVersion:"'.WG::vars("appVersion").'"',

				'wg_url:"'.$url.'"',

				'wg_lastUpdate:'.time(),

				'wg_updateDelay:'.(WG::vars('ui_ws_refresh') * 1000),

				'wg_sessionAge:'.WG::vars('session_age'),

				'wg_logged:'.($user !== null ? 'true' : 'false')

		), $javascript);*/

		include WG::base('modules/core/template.core.html');

	}

	

	/**

	 * Construit le fichier de styles CSS à partir des données des modules.

	 * 

	 * Dans un premier temps, quand le client charge l'application HTML WG, il recoit

	 * le fichier style.css du répertoire public, qui contient uniquement les styles

	 * globaux à toute l'application. S'il s'authentifie, il aura accès aux styles des modules.

	 * 

	 * Pour simplifier l'utilisation des CSS, les modules déclarent une propriété 'stylesheet' dans

	 * leurs manifests pour pointer vers les fichiers à inclure. A l'intérieur des fichiers CSS,

	 * les chemins sont relatifs au  répertoire 'public' des modules. Par exemple, si dans un fichier

	 * on a '#truc { background: url(machin.png); }', alors la réécriture automatique fera pointer

	 * vers le fichier 'http[s]://[host]/wg/modules/[module]/public/machin.png'.

	 * Pour pouvoir indiquer des chemins par rapport au répertoire public de toute l'installation,

	 * il faut commencer le chemin par un slash, example : '#truc { background: url(/chouette.jpg); }'

	 * 

	 * TODO Compresser le code avec SASS ?

	 * TODO Ne renvoyer que les styles des modules où l'utilisateur peut voir au moins une vue ?

	 * 

	 * @return string Le code CSS assemblé et réécrit.

	 */

	public static function stylesheets() {

		$data = '';

		// Fetch modules

		foreach (self::$instance->modules as $modName => $modProp) {

			if (isset($modProp['stylesheets'])) {

				foreach ($modProp['stylesheets'] as $file) {

					$tmp = @file_get_contents(WG::base($file));

					if ($tmp !== false) {

						// Paths

						$tmp = str_replace(

								array(

										'url(/',

										'url(',

										'url--('

								),

								array(

										'url--(',

										'url(' . WG::url('modules/'.$modName) . '/public/',

										'url(' . WG::url() . '/../../public/'

								),

								$tmp

						);

						// Pack

						$tmp = str_replace(

								array("\t", "\r", "\n\n"),

								array('', '', "\n"),

								$tmp

						);

						$data .= $tmp . "\n";

					}

					unset($tmp);

				}

			}

		}

		return $data;

	}

	

	/**

	 * Assemble le code Javascript des modules.

	 * 

	 * Pour plus de détails, le comportement se rapproche de WG::css() sans la réécriture.

	 * 

	 * @return string Le code Javascript assemblé

	 * @deprecated

	 */

	public static function javascripts() {

		trigger_error("WG::javascripts() is deprecated", E_USER_DEPRECATED);

		return '';

	}



	/**

	 * Renvoi un tableau contenant tous les paramètres des menus.

	 *

	 * TODO Mise en cache

	 *

	 * @return array

	 */

	public static function menus() {

		$r = array();

		// Fetch modules

		foreach (self::$instance->modules as $modName => $module) {

			if (isset($module['menu'])) {

				foreach ($module['menu'] as $menuProp) {

					$menuProp['module'] = $modName;

					$r[] = $menuProp;

				}

			}

		}

		// Order modules

		$s = array();

		foreach ($r as $m) {

			if (isset($m['position'])) {

				$s[$m['position']][] = $m;

			}

			else {

				$s[10][] = $m;

			}

		}

		ksort($s);

		// Final

		$r = array();

		foreach ($s as $m) {

			$r = array_merge($r, $m);

		}

		return $r;

	}



	############################################ P E R S I S T E N C E

	

	public static function persistenceExists($class, $id) {

		$dir = WG::base('data/persistence');

		$uid = self::persistenceUID($class, $id);

		return is_file("$dir/$uid");

	}

	

	public static function persistenceStore(Soho_Serializable $data) {

		// TODO Il faudrait grouper l'enregistrement à la fin de l'execution du script

		$dir = WG::base('data/persistence');	

		$uid = self::persistenceUID(get_class($data), $data->getSerializableUID());

		return @file_put_contents("$dir/$uid", serialize($data)) !== false;

	}

	

	public static function persistenceUID($class, $id) {

		$uid = md5("$class:$id");

		return $class . '{' . substr($uid, 0, 8) . '-' . substr($uid, 8, 8) . '-' .

			substr($uid, 16, 8) . '-' . substr($uid, 24, 8) . '}';

	}

	

	public static function persistenceRestore($class, $id) {

		$dir = WG::base('data/persistence');

		$uid = self::persistenceUID($class, $id);

		if (!is_file("$dir/$uid")) {

			return null;

		}

		$ct = @file_get_contents("$dir/$uid");

		if (!$ct) {

			return false;

		}

		return unserialize($ct);

	}

	

	public static function persistencePurge($class, $id = null) {

		$dir = WG::base('data/persistence');

		// Purge globale d'un type d'objet

		if ($id === null) {

			$r = true;

			foreach (scandir($dir) as $file) {

				if ($file == '.' || $file == '..') continue;

				$r = $r && unlink("$dir/$file");

			}

			return $r;

		}

		// Purge d'un fichier uniquement

		else {

			$uid = self::persistenceUID($class, $id);

			if (!is_file("$dir/$uid")) {

				return true;

			}

			return unlink("$dir/$uid");

		}

	}

	

	############################################ U T I L S



	/**

	 * @param string $modelName Model name of the target

	 * @param int|string $targetId Target id

	 * @param string $targetName Target name

	 * @param enum[create,edit,delete,comment,changestatus] $action Between: 

	 * @param string $log Message

	 * @param Moodel|null|true $user The user. Null = current user. True = System user.

	 *

	 * @throw Exception

	 */

	public static function log($modelName, $targetId, $targetName, $action, $log, $user=null) {

		if ($user === null) {

			$user = WG::user();

		}

		else if ($user === true) {

			$user = WG::sysuser();

		}

		if (!is_object($user)) {

			throw new WGException("unable to log because user is null ($user)");

		}

		$model = ModelManager::get('Log')->new

			->set('creation', time())

			->set('user', $user->id)

			->set('target_type', $modelName)

			->set('target_name', $targetName)

			->set('target_id', $targetId)

			->set('action', $action)

			->set('log', $log);

		return $model->save();

	}

	

	/**

	 * Quitter l'execution et lever une erreur. 

	 *

	 * @param string $msg

	 * @throws WGSecurityException

	 * @deprecated

	 */

	public static function quit($msg) {

		throw new WGSecurityException($msg);

	}



	/**

	 * Formate la date relativement à la date actuelle.

	 *

	 * @param int $timestamp

	 * @return string

	 * @see rdate()

	 */

	public static function rdate($timestamp) {

		return rdate($timestamp);

	}

	

	public static function bootlog($log) {

		$traces = debug_backtrace();

		self::$bootlogs[] = array($log, time(), $traces);

	}

	

	public static function printBootLogs($verbose = 0) {

		echo "<pre>\n";

		$i = 0;

		function trace2string($trace, $verbose) {

			$r = '';

			if (isset($trace['class'])) {

				$r .= $trace['class'].$trace['type'];

			}

			if (isset($trace['function'])) {

				$r .= $trace['function'];

				$r .= '(';

				if (isset($trace['args'])) {

					$c = 0;

					foreach ($trace['args'] as $arg) {

						if ($c > 0) {

							$r .= ', ';

						}

						if (is_object($arg)) {

							$r .= get_class($arg);

						}

						else if (is_bool($arg)) {

							$r .= $arg ? 'true' : 'false';

						}

						else if (is_numeric($arg)) {

							$r .= $arg;

						}

						else if (is_string($arg)) {

							$r .= '"' . addslashes($arg) . '"';

						}

						else {

							$r .= gettype($arg);

						}

						$c++;

					}

				}

				$r .= ')';

				if ($verbose > 1) {

					$r .= " in {$trace['file']} line {$trace['line']}";

				}

			}

			return $r;

		}

		$cache = array();

		foreach (self::$bootlogs as $log) {

			list($msg, $ctime, $traces) = $log;

			echo date('Y/m/d H:i', $ctime);

			

			// Traitement des exceptions

			if ($msg instanceof Exception) {

				echo " *** " . get_class($msg);

				echo $msg->getTraceAsString();

				continue;

			}



			// Suppression des mauvaises traces

			if (isset($traces[0]['function']) && $traces[0]['function'] == 'bootlog' && sizeof($traces) > 1) {

				array_shift($traces);

			}

			

			echo " *** ";

			if (sizeof($msg = explode('>', "$msg", 2)) > 1) {

				echo strtoupper($msg[0]) . '>' . $msg[1];

			}

			else {

				echo strtoupper($msg[0]);

			}

			

			if ($verbose > 0) {

				foreach ($traces as $i => $trace) {

					if ($i === 0) continue;

					$str = trace2string($trace, $verbose);

					if ($i > 2 && isset($cache[$str])) continue;

					echo "\n\t[" . ($i === 1 ? '~' : $i - 1) . "] $str";

					$cache[$str] = true;

				}

			}

			else {

				$source = array_shift($traces);

				if ($source) {

					echo "\n\tSource: " . trace2string($source, $verbose);

				}

				$caller = array_shift($traces);

				if ($caller) {

					echo "\n\tCaller: " . trace2string($caller, $verbose);

				}

			}

			echo "\n";

		}

		echo "</pre>\n";

	}



}



/**

 * @deprecated

 */

function json2array($json) {

	if (is_array($json) || $json instanceof stdClass) {

		$r = array();

		foreach ($json as $k => $v) {

			$r[$k] = json2array($v);

		}

		return $r;

	}

	else {

		return $json;

	}

}



require_once 'common.php';



interface WGSession {



	/**

	 * @return boolean

	 */

	public function isLogged();



	/**

	 * @return Moodel<TeamMember>

	 */

	public function getUser();



	/**

	 * @return void

	 */

	public function logout();

	

	/**

	 * @return void

	 */

	public function auth();



	/**

	 * @return void

	 */

	public function destroy();



	/**

	 * @return string

	 */

	public function loginform();



	/**

	 * @return WGSecurityPolicy The current custom security policy.

	 */

	public function setCustomSecurityPolicy(WGSecurityPolicy $policy=null);



}



interface WGSecurityPolicy {



	/**

	 * Called before user's login.

	 *

	 * @param Moodel<TeamMember> $user The user who disconnected.

	 * @param WGSession $session The current session.

	 * @return boolean Return false to stop login process.

	 */

	public function onLogin(Moodel $user, WGSession $session);



	/**

	 * Called before user's logout.

	 *

	 * @param WGSession $session The current session.

	 * @param string $reason

	 */

	public function onLogout(WGSession $session, $reason);



}



interface Soho_Plugin {

	

	/**

	 * @return string

	 */

	public function getPluginName();

	

	/**

	 * @param string $name

	 * @return boolean

	 */

	public function hasAPI($name);

	

	/**

	 * @param string $name

	 * @return object

	 */

	public function getAPI($name);



	/**

	 * @throw Exception

	 */

	public function init();

	

}



abstract class Soho_PluginBase implements Soho_Plugin {

	

	protected $pluginName;

	protected $api = array();

	

	/**

	 * 

	 * @param string $pluginName

	 */

	public function __construct($pluginName) {

		$this->pluginName = $pluginName;

	}

	

	/**

	 * (non-PHPdoc)

	 * @see Soho_Plugin::getPluginName()

	 */

	public function getPluginName() {

		return $this->pluginName;

	}

	

	/**

	 * (non-PHPdoc)

	 * @see Soho_Plugin::hasAPI()

	 */

	public function hasAPI($name) {

		return isset($this->api[$name]);

	}

	

	/**

	 * (non-PHPdoc)

	 * @see Soho_Plugin::getAPI()

	 */

	public function getAPI($name) {

		return $this->api[$name];

	}

	

}



// must stay here

function wgcrt_log_exception(Exception $ex) {

	$log = array();

	$log[] = 'TokenId: ';

	$log[] = session_id();

	$log[] = PHP_EOL;

	$log[] = 'Exception: ';

	$log[] = get_class($ex);

	$log[] = ' ';

	$log[] = $ex->getMessage();

	$log[] = PHP_EOL;

	$log[] = $ex->getTraceAsString();

	/*$i = 1;

	foreach ($ex->getTrace() as $trace) {

		$log[] = PHP_EOL;

		$log[] = str_pad("$i.", 3);

		if (isset($trace['class'])) {

			$log[] = $trace['class'];

			$log[] = '::';

			$log[] = $trace['function'];

			$log[] = '()';

		}

		else if (isset($trace['function'])) {

			$log[] = $trace['function'];

			$log[] = '()';

		}

		$log[] = ', in ';

		$log[] = $trace['file'];

		$log[] = ':';

		$log[] = $trace['line'];

	}*/

	$log[] = PHP_EOL;

	file_put_contents(

		WG::base('data/wgcrt.log'),

		implode('', $log),

		FILE_APPEND

	);

}



?>

