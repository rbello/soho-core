<?php

/**
 * Gestionnaire de session.
 * 
 * Pour l'initialisation:
 * 
 * WGCRT_Session::init();
 * $session = WGCRT_Session::getSession();
 * $session->start();
 * 
 * @author ted
 * @version 2.0
 */
final class WGCRT_Session implements WGSession {

	public static $publicRealm = 'Connection';
	public static $allowIPChange = false;
	public static $allowAgentChange = false;
	public static $sendNotificationMail = false;
	public static $synchPhpSessionVar = true;
	public static $changeSaltAfterLogin = true;
	public static $ttl_session = 3600; // seconds (1 heure)
	public static $ttl_sudo = 900; // seconds (15 minutes)
	public static $hideFormFields = false;
	public static $debugMode = false;
	public static $logfile = './../data/wgcrt.log';
	public static $debugfile = './../data/wgcrt-debug.log';

	/**
	 * @var boolean $initialized Indique si la session a été initialisée.
	 */
	private static $initialized = false;

	/**
	 * @var MoodelStruct<UserSession> $struct La structure du model de session.
	 */
	private static $struct_session = null;

	/**
	 * @var MoodelStruct<TeamMember> $struct La structure du model d'utilisateur.
	 */
	private static $struct_user = null;

	/**
	 * @var UserSession[] $cache Session stoquées en cache.
	 */
	private static $cache = array();
	
	/**
	 * @var WGCRT_Session|null $sessionStarted Indique la session qui a été démarrée.
	 * Il ne peut y avoir qu'une session ouverte en même temps, car c'est elle qui sera maj et qui sera
	 * synchronisée avec la session PHP.
	 */
	private static $sessionStarted = null;

	/**
	 * @var Moodel<UserSession> $model L'instance du model derrière la session.
	 */
	private $model = null;

	/**
	 * @var mixed[] $data Ce tableau contient toutes les données de session, ainsi que les données user.
	 */
	private $data = array();
	
	/**
	 * @var string $error Erreur courante.
	 */
	public $error = '';

	/**
	 * @var WGSecurityPolicy|null $policy Politique de gestion de la sécurité customisée.
	 */
	private static $policy = null;
	
	/**
	 * @var Closure[] Callbacks à lancer lors du travail du garbage collector. 
	 */
	private static $gcListeners = array();
	
	/**
	 * Niveau de protection de la session.
	 * Voir les constantes WGCRT_QOP_*
	 * @var int
	 */
	private $qop = 0;


	############################################  L I F E   C Y C L E

	/**
	 * Initialisation de la classe WGCRT_Session
	 *
	 * @param boolean $startPHPSession Indique si la session PHP doit être lancée
	 * @throws WGSecurityException Si la l'initialisation foire
	 */
	public static function init($startPHPSession = false) {
		
		if (!self::$initialized) {
			
			// Paths
			self::$logfile = self::fixPath(self::$logfile);
			self::$debugfile = self::fixPath(self::$debugfile);
			
			// Debug
			if (self::$debugMode) {
				self::debug("---------------------------");
				self::debug("init start-php-session='$startPHPSession' request='".@$_SERVER['REQUEST_URI']."'");
			}
			
			// Headers protégées
			if (!headers_sent()) {
				header('Server: Apache (Win) PHP');
				header('X-Powered-By: SoHo v3');
				header('X-Frame-Options: SAMEORIGIN');
			}
					
			// Authentification un peu plus forte et qui demande l'obtention d'un salt
			define('WGCRT_QOP_SALT',			4);
			
			// Authentification avec en plus du salt une APIKEY déjà connue par le client avant la connexion
			define('WGCRT_QOP_APIKEY',			8);
			
			// Secured transport layer : la couche de transport est sécurisée (SSL/TLS)
			define('WGCRT_QOP_STL',				16);
			
			// Le contenu est crypté en plus en AES (avec jCryption)
			define('WGCRT_QOP_AES',				32);
			
			// La connexion est faite à partir d'un emplacement connu et validé
			define('WGCRT_QOP_SAFE_PLACE',		64);
			
			// La connexion a passée le test du keyring
			define('WGCRT_QOP_KEYRING',			128);
			
			// La connexion passe par un relais tor
			define('WGCRT_QOP_TOR',				128);
			
			// Initialized
			self::$initialized = true;
			
			// Save structure
			self::$struct_user = ModelManager::get('TeamMember');
			self::$struct_session = ModelManager::get('UserSession');
			
			// Garbage collector
			self::gc();
			
			// PHP Session
			if ($startPHPSession) {
				self::phpSessionStart();
			}
			
		}
		
	}

	/**
	 * Constructeur de la classe WGCRT_Session.
	 *
	 * @param Moodel<UserSession> $model
	 * @param boolean $create Créer la session, sinon c'est un restore
	 */
	private function __construct(Moodel $model, $create = false) {
		
		// Save session model
		$this->model = $model;
		
		// Save default qop
		$this->qop = null;
		
		// Debug
		if (self::$debugMode) {
			$this->debug("construct create='$create'", $this);
		}
		
		// Create session data
		if ($create) {
			$this->reset(true);
		}
		
		// Restore
		else {
			$this->data = $model->data;
		}
		
	}

	/**
	 * Lance l'execution de la session.
	 * Cette méthode doit être appelée pour que la session soit active, et qu'elle soit synchronisée avec
	 * la session PHP.
	 */
	public function start() {
		
		// On verifie qu'une seule session puisse être lancée
		if (self::$sessionStarted != null) {
			throw new WGSecurityException('A session was allready started');
		}
		self::$sessionStarted = $this;
		
		// Debug
		if (self::$debugMode) {
			$this->debug("start", $this, 2);
			$this->debug("restore-data-from-db length='".sizeof($this->model->get('data'))."'", $this, 4);
		}
		
		// Restore data
		$this->data = $this->model->get('data');
		
		// Auto-init
		if (!isset($this->data['salt'])) {
			$this->reset();
		}

		// Rewrite $_SESSION var
		if (self::$synchPhpSessionVar) {
			if (self::$debugMode) {
				$this->debug("php-session action='write' length='".sizeof($this->data['user_vars'])."'", $this, 4);
			}
			$_SESSION = $this->data['user_vars'];
		}

		// Flood protection
		$this->flood();

		// Update
		$this->update();
	
		// Register shutdown function
		register_shutdown_function(array($this, 'write'));
		
		// Return this
		return $this;
	}

	/**
	 * Initialise les données de la session.
	 */
	private function reset($resetUserVars=true) {
		
		// Debug
		if (self::$debugMode) {
			$this->debug("reset reset-user-vars='$resetUserVars'", $this, 2);
		}
				
		// Les données utilisateur, en fonction de si elles doivent être remises à 0 ou non
		$data = $resetUserVars ? array() : $this->data['user_vars'];
		
		// Modifier des données
		$this->data = array(
			'salt' => getRandomKey(128),
			'form_id' => self::$hideFormFields ? self::minikey(32) : 'loginform-wgcrt',
			'field_user' => self::$hideFormFields ? self::minikey(32) : 'login-wgcrt',
			'field_pwd' => self::$hideFormFields ? self::minikey(32) : 'password-wgcrt',
			'field_submit' => self::$hideFormFields ? self::minikey(32) : 'submit-wgcrt',
			'auth_qop' => null,
			'flood_time' => time(),
			'flood_counter' => 0,
			'hostname' => isset($_SERVER['REMOTE_ADDR']) ? gethostbyaddr($_SERVER['REMOTE_ADDR']) : '-',
			'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '(none)',
			'user_vars' => $data
		);
		
		// On enregistre l'état de la session
		$this->model
			->set('user_true', null)
			->set('user_used', null)
			->set('data', $this->data)
			->save();
		
		// Debug
		if (self::$debugMode) {
			$this->debug("salt regenerate value='".$this->data['salt']."'", $this, 4);
			$this->debug("save", $this, 4);
		}
			
	}

	/**
	 * Mise à jour de la session.
	 * Les champs 'security', 'ip_addr' et 'last_request_dt' sont mis à jour mais le model n'est pas enregistré.
	 */
	private function update() {
		
		// Mode de sécurisation
		$https = (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
			 (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on');
		
		// Hostname
		$host = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '-';
		
		// QoP
		if ($https) {
			$this->qop |= WGCRT_QOP_STL;
		}
		
		// Mise à jour du model
		$this->model
			->set('security', $https ? 'SSL' : '-')
			->set('ip_addr', $host)
			->set('last_request_dt', $_SERVER['REQUEST_TIME']);
		
		// Debug
		if (self::$debugMode) {
			$this->debug("update last-request-dt=" . $_SERVER['REQUEST_TIME'], $this, 4);
		}
		
	}

	/**
	 * Lancement du garbage collector.
	 * Ce processus va supprimer toutes les sessions expirées.
	 *
	 * @throw WGSecurityException Si la classe n'est pas initialisée.
	 */
	public static function gc() {
		
		// Test de l'initialisation
		if (!self::$initialized) {
			throw new WGSecurityException('WGCRT_Session class not initialized');
		}

		// Recupération de la base de données
		$db = self::$struct_session->_db;
		
		// Le nom de la table des sessions 
		$table = '`'.$db->getDatabaseName().'`.`'.$db->getPrefix().'usersession`';

		// On passe à la suppression des sessions expirées.
		// S'il n'y a aucun listener, on ne s'ennuie pas à créer les objets de la session : on supprime
		if (sizeof(self::$gcListeners) < 1) {
			$sql = "DELETE FROM $table WHERE `last_request_dt` + " . self::$ttl_session . ' < ' . $_SERVER['REQUEST_TIME'];
			$d = $db->query($sql);
		}
		// Sinon, on va créer les objets pour propager des events
		else {
			// On recupère les sessions expirées
			$sessions = $db->query("SELECT * FROM $table WHERE `last_request_dt` + " . self::$ttl_session . ' < ' . $_SERVER['REQUEST_TIME']);
			$d = $sessions->size();
			// Si il y en a
			if ($d > 0) {
				// On fabrique des objets de type Moodel<UserSession>
				$sessions = self::$struct_session->createMoodels($sessions);
				// On parcours les sessions
				foreach ($sessions as $session) {
					// On fabrique un vrai objet WGCRT_Session
					$session = new WGCRT_Session($session, false);
					// Et on propage l'events
					self::triggerGcEvent('expire', $session);
					// TODO Et lancer self::$policy->onLogout($session, 'timeout');
					// Puis on supprime la session sans lancer d'event car 'expire' est déjà parti
					$session->destroy(false);
				}
			}
			// On vide la mémoire
			unset ($sessions, $session);
		}

		// Réinitialisation du sudo
		$sql = "UPDATE $table SET `user_used` = `user_true` WHERE `last_request_dt` + " . self::$ttl_sudo . ' < ' . $_SERVER['REQUEST_TIME'];
		// TODO Ici, il faudrait propager un event ?
		$s = $db->query($sql);
		
		// Debug
		if (self::$debugMode) {
			self::debug("gc delete='$d' unsudo='$s'");
		}
	}

	/**
	 * Enregistre les données de la session dans la base de donnée.
	 * L'appel à cette méthode est automatique à partir de la callback de shutdown UNIQUEMENT si cette
	 * session a été démarrée (start). Si elle ne l'a pas été, toutes les modifications comme sudo ou login
	 * ne seront pas enregistrée tant que cette méthode ne sera pas appelée.
	 */
	public function write () {

		// On vérifie qu'elle n'ai pas été détruite entre temps
		if ($this->isDestroyed()) {
			if (self::$debugMode) {
				self::debug("write error='session destroyed'");
			}
			return;
		}
		
		// Debug
		if (self::$debugMode) {
			self::debug("write", $this);
		}
		
		// On synchronise avec la session PHP
		if (self::$synchPhpSessionVar && isset($_SESSION)) {
			if (self::$debugMode) {
				self::debug("php-session action='read' length='".sizeof($_SESSION)."'", $this, 2);
			}
			$this->data['user_vars'] = $_SESSION;
		}
		
		// On met à jour les données de la session
		$this->model->set('data', $this->data);
		
		// On erengistre en bdd
		$r = $this->model->save();
		if (self::$debugMode) {
			self::debug("save result='$r'", $this, 2);
		}
		
	}

	/**
	 * Supprime la session.
	 * L'objet ne sera plus utilisable après l'appel à cette méthode.
	 * 
	 * @param boolean $triggerEvent Si TRUE alors un event GC de type 'destroy' sera lancé.
	 * @return void
	 */
	public function destroy($triggerEvent = true) {
		
		// Debug
		if (self::$debugMode) {
			$this->debug("destroy", $this, 2);
		}
		
		// Propagation d'un event
		if ($triggerEvent) {
			self::triggerGcEvent('destroy', $this);
		}
		
		// On sauvegarde le SID
		$sid = $this->getSID();

		// Delete session from cache
		unset(self::$cache[$this->model->get('key')]);

		// Delete session model
		$this->model->delete();
		
		// On conserve le session ID (pour les logs essentiellement)
		$this->model = $sid;
		
		// Delete object data
		$this->data = null;
		
		// On s'assure que l'instance ne soit plus active
		self::$sessionStarted = null;
		
		// Close PHP session (only if started)
		if ($this->isStarted() && session_name() != '') {
			// Debug
			if (self::$debugMode) {
				$this->debug("php-session action='destroy'", $sid, 4);
			}
			if (ini_get('session.use_cookies')) {
				$params = session_get_cookie_params();
				@setcookie(
					session_name(),
					'',
					time() - 42000,
					$params['path'],
					$params['domain'],
					$params['secure'],
					$params['httponly']
				);
			}
			@session_regenerate_id();
			@session_destroy();
			$_SESSION = array();
		}
		
		// On log un save pour bien indiquer que la db est altérée
		if (self::$debugMode) {
			$this->debug("save action='delete'", $sid, 4);
		}
		
	}

	############################################  A C C E S S O R S

	
	/**
	 * Renvoi l'instance de la session démarrée.
	 * Comme il ne peut y avoir qu'une session démarrée, et qu'elle doit absolument
	 * être démarrée pour fonctionner, cette méthode est idéale pour récupérer
	 * la session depuis le code extérieur.
	 * Si aucune session n'a été initialisé, cette méthode renvoi NULL. 
	 * 
	 * @return WGCRT_Session|null
	 */
	public static function startedSession() {
		return self::$sessionStarted;
	}
	
	/**
	 * Convertir une liste de models de UserSession en sessions WGCRT.
	 *
	 * @param Moodel<UserSession>[] $array Liste des models à convertir en sessions.
	 * @return WGCRT_Session[]
	 */
	private static function createSessions($array) {
		
		// Le tableau de sortie
		$sessions = array();
		
		// On parcours les models 
		foreach ($array as $model) {
			
			// La clé sert à comparer la session avec le cache
			$key = $model->get('key');
			
			// Si la session existe en cache on recupère l'instance en cache
			// et on l'ajoute au tableau de sortie
			if (isset(self::$cache[$key])) {
				$sessions[] = self::$cache[$key];
			}
			
			// Sinon on initialise une nouvelle session 
			else {
				self::$cache[$key] = new WGCRT_Session($model);
				// Et on l'enregistre en cache
				$sessions[] = self::$cache[$key];
			}
		}
		
		// On renvoi la liste de sessions créée
		return $sessions;
		
	}

	/**
	 * Recupérer une session à partir de son SID.
	 * Cette fonction va renvoyer un objet de type WGCRT_Session. La session sera juste restaurée mais pas
	 * lancée.
	 * 
	 * @param int $sid L'identifiant de la session dans la base de donnée (pas la clé, l'id)
	 * @return WGCRT_Session|null
	 * @throws Exception Si la classe WGCRT n'est pas initialisée. 
	 */
	public static function getById($sid) {
		
		// Test d'initialisation
		if (!self::$initialized) {
			throw new Exception('WGCRT_Session class not initialized');
		}
		
		// Recherche dans la base d'une entrée avec cet SID
		$model = self::$struct_session->getById($sid, 1);
		
		// Si le model n'existe pas, la session est introuvable
		if (!$model) {
				return null;
		}
		
		// On vérifie que la session ne soit pas déjà dans le cache
		// Si oui, on renvoi cette instance.
		if (isset(self::$cache[$model->get('key')])) {
			return self::$cache[$model->get('key')];
		}
		
		// Debug
		if (self::$debugMode) {
			self::debug("getById action='restore' type='".$model->get('type')."' key='".substr($model->get('key'), 0, 10)."'");
		}
		
		// Construction de l'objet
		$session = new WGCRT_Session($model);
		
		// Enregistrement dans le cache
		self::$cache[$model->get('key')] = $session;
			
		// On renvoi l'instance de la session
		return $session;
	}

	/**
	 * Renvoi un tableau contenant toutes les sessions actuelles.
	 * Le garbage collector est lancé avant l'appel à cette méthode, donc seul les sessions
	 * valides seront renvoyées.
	 *
	 * @return WGCRT_Session[]
	 * @throws Exception Si la classe WGCRT n'est pas initialisée.
	 */
	public static function getSessions() {
		
		// Test d'initialisation
		if (!self::$initialized) {
			throw new Exception('WGCRT_Session class not initialized');
		}
		
		// On recherche toutes les sessions à partir de la bdd
		return self::createSessions(self::$struct_session->all());
	}

	/**
	 * Renvoi toutes les sessions d'un utilisateur donné.
	 * 
	 * @param Moodel $user
	 * @param boolean $used Indique si c'est les sessions où l'utilisateur est utilisé qui doivent
	 *   être renvoyée, ou bien si c'est les sessions que l'utilisateur possède réellement (par défaut).
	 * @return WGCRT_Session[]
	 * @throws Exception Si la classe WGCRT n'est pas initialisée.
	 */
	public static function getUserSessions(Moodel $user, $used = false) {
		if (!self::$initialized) {
			throw new Exception('WGCRT_Session class not initialized');
		}
		return self::createSessions(self::$struct_session->get(array(
			($used ? 'user_used' : 'user_true') => $user->get('id')
		)));
	}

	/**
	 * Recupére une session à partir d'une clé et d'un type de session.
	 * Les deux paramètres peuvent être ignorés : dans ce cas la méthode va automatiquement
	 * les déterminer si c'est possible.
	 * Sauf exceptions, cette méthode va toujours renvoyer une session, qu'elle soit obligée
	 * de la créer ou bien de la restaurer.
	 * Il est neccessaire pour le code d'initialisation de faire au moins un appel à cette
	 * méthode pour la créer et pouvoir l'utiliser. Par contre elle ne sera pas directement
	 * activée (ce qui se fait avec la méthode start).
	 * 
	 * @param string|null $type
	 * @param string|null $key
	 * @return WGCRT_Session
	 * @throws Exception Si la classe WGCRT n'est pas initialisée
	 * @throws WGSecurityException S'il est impossible de déterminer la clé de session
	 */
	public static function getSession($type = null, $key = null) {

		// Test d'initialisation
		if (!self::$initialized) {
			throw new Exception('WGCRT_Session class not initialized');
		}

		// Par défaut, le type corresponds au type d'API
		$type = is_string($type) ? $type : PHP_SAPI;
		// On recupère la clé automatiquement si c'est neccessaire
		$key = is_string($key) ? $key : self::guessSessionKey($type);

		if (isset(self::$cache[$key])) {
			return self::$cache[$key];
		}

		$sessions = self::$struct_session->get(array(
			'type' => substr($type, 0, 10),
			'key' => substr($key, 0, 40)
		));

		if (sizeof($sessions) > 0) {

			if (self::$debugMode) {
				self::debug("getSession action='restore' type='$type' key='".substr($key, 0, 10)."'");
			}
		
			$session = new WGCRT_Session($sessions[0]);
			
			self::$cache[$key] = $session;
			
			return $session;

		}

		if (self::$debugMode) {
			self::debug("getSession action='create' type='$type' key='".substr($key, 0, 10)."'");
		}

		$model = self::$struct_session->new
			->set('type', $type)
			->set('key', $key)
			->set('start_dt', $_SERVER['REQUEST_TIME'])
			->set('data', array());

		$session = new WGCRT_Session($model, true);

		self::$cache[$key] = $session;

		//$model->save();

		return $session;

	}

	/**
	 * Tente de deviner la clé de session à partir des éléments présents dans la variable globale $_SERVER.
	 *
	 * @param string $type Le type de session.
	 * @return string La clé de session en SHA1 (length = 39)
	 * @throw WGSecurityException S'il est impossible de déterminer la clé de session.
	 */
	private static function guessSessionKey($type=PHP_SAPI) {

		if (!isset($_SERVER)) {
			throw new WGSecurityException('Unable to guess session key: no global var $_SERVER');
		}

		$key = array();

		if (isset($_SERVER['USER'])) {
			$key[] = $_SERVER['USER'];
		}
		if (isset($_SERVER['WINDOWID'])) {
			$key[] = $_SERVER['WINDOWID'];
		}
		if (isset($_SERVER['GNOME_KEYRING_PID'])) {
			$key[] = $_SERVER['GNOME_KEYRING_PID'];
		}
		if (isset($_SERVER['GNOME_KEYRING_PID'])) {
			$key[] = $_SERVER['GNOME_KEYRING_PID'];
		}
		if (isset($_SERVER['XDG_SESSION_COOKIE'])) {
			$key[] = $_SERVER['XDG_SESSION_COOKIE'];
		}
		if (!self::$allowIPChange && isset($_SERVER['REMOTE_ADDR'])) {
			$key[] = $_SERVER['REMOTE_ADDR'];
		}
		if (!self::$allowAgentChange && isset($_SERVER['HTTP_USER_AGENT'])) {
			$key[] = $_SERVER['HTTP_USER_AGENT'];
		}
		if (function_exists('session_id') && session_id() != '') {
			$key[] = session_id();
		}

		// Debug
		//$key[] = $_SERVER['REQUEST_TIME'];
		//print_r($key);

		// Security
		if (sizeof($key) < 1) {
			throw new WGSecurityException('Unable to guess session key: not enought token');
		}

		$key[] = $type;

		return sha1(implode(':', $key));

	}

	############################################  A U T H E N T I C A T I O N

	/**
	 * Renvoi la valeur du salt token de la session.
	 *
	 * @return string
	 */
	public function getSalt() {
		return $this->data['salt'];
	}

	/**
	 * Tente l'authentification à partir de la requête POST.
	 *
	 * Cette méthode ne renvoi aucun résultat. Il faut ensuite utiliser $this->isAuthed().
	 */
	public function auth() {

		// Using POST data only
		if (!isset($_POST[$this->data['field_user']]) || !isset($_POST[$this->data['field_pwd']])) {
			return;
		}

		// Fix time response vulnerability
		// Ce petit bout de code permet de contrer les attaques qui se basent sur le temps de réponse
		// du système d'authentification.
		usleep(rand(500, 500000));

		// Recupération du salt token
		$salt = $this->data['salt'];

		// Recupération du nom du champs user
		$user = $_POST[$this->data['field_user']];

		// Le password est composé de QOP:PASSWORD
		$tmp = explode(':', $_POST[$this->data['field_pwd']], 2);
		if (sizeof($tmp) < 2) {
			$this->error = 'Invalid request';
			return;
		}
		list($qop, $pwd) = $tmp;

		// Database init
		$db = DatabaseConnectorManager::getDatabase('main');
		$salt = $db->escapeString($salt);
		$pwd = $db->escapeString($pwd);
		$user = $db->escapeString($user);

		// Create password query according to QoP (Quality of Protection)
		switch ($qop) {
			// Securized : (SALT:(USER:PASSWORD):APIKEY)
			case 's' :
				$sql = "SHA1(CONCAT_WS(':', '$salt', `password`, `apikey`))";
				break;
			// Basic : (SALT:(USER:PASSWORD))
			case 'b' :
				$sql = "SHA1(CONCAT_WS(':', '$salt', `password`))";
				break;
			// Minimum : (USER:PASSWORD)
			/*case 'm' :
				$sql = "SHA1(CONCAT_WS(':', `password`))";
				break;*/
			// Error
			default :
				$this->log("UNKNOWN QOP $qop");
				$this->error = "Unkown QOP ($qop)";
				return;
		}		// QoP

		// Debug
		if (self::$debugMode) {
			$this->debug("auth user='$user' qop='$qop' password='$pwd' salt='$salt'", $this, 2);
		}

		// Execute query
		// TODO Gêrer la suppression/désactivation des comptes
		$r = $db->query("SELECT * FROM `".WG::vars('db_prefix')."teammember` WHERE `login` = '$user' AND $sql = '$pwd' AND `password` != '' LIMIT 1;");

		// User not found, login invalid
		if ($r->size() !== 1) {
			// Debug
			if (self::$debugMode) {
				$this->debug("auth-error message='no sql result'", $this, 4);
			}
			// Error
			$this->error = 'Bad login/password';
			// Log
			$this->log("LOGIN FAILURE: User=$user, Password=$pwd, QoP=$qop");
			// Return
			return;
		}

		// Create user model
		$account = ModelManager::get('TeamMember')->createMoodel($r->current());

		// Custom security policy
		if (self::$policy !== null) {
			if (self::$policy->onLogin($account, $this) === false) {
				// Debug
				if (self::$debugMode) {
					$this->debug("auth-error message='security policy'", $this, 4);
				}
				// Error
				$this->error = 'Security policy';
				// Log
				$this->log("LOGIN STOPPED BY SECURITY POLICY: {$account->login} (QoP = $qop)");
				// Stop process
				return;
			}
		}

		// Login user account
		$this->login($account);

		// Save account data
		$this->data['auth_qop'] = $qop;
		
		// QoP
		if ($qop === 'b') {
			$this->qop |= WGCRT_QOP_SALT;
		}
		else if ($qop === 's') {
			$this->qop |= WGCRT_QOP_SALT;
			$this->qop |= WGCRT_QOP_APIKEY;
		}

		// New salt
		if (self::$changeSaltAfterLogin) {
			$this->data['salt'] = getRandomKey(128);
			if (self::$debugMode) {
				$this->debug("salt regenerate value='".$this->data['salt']."'", $this, 4);
			}
		}

		// Protect account password (note: supprimer le champ password pour que la suite du script n'y ai pas
		// access, mais pas comme ca car s'il y'a un save() c'est foutu...)
		//$this->account->password = null;

		// Log
		$this->log("LOGIN SUCCESSFUL: {$account->login} (QoP = $qop)");

		// Mail
		if (self::$sendNotificationMail) {
			if (self::$debugMode) {
				$this->debug("notification-mail send", $this, 4);
			}
			$this->sendNotificationMail();
		}

	}

	############################################  O L D

	/*public function __construct($session_name=null) {

		// Save common data
		$this->addr = @$_SERVER['REMOTE_ADDR'];
		$this->agent = @$_SERVER['HTTP_USER_AGENT'];


		// Flood detection

		// Register shutdown function
		//register_shutdown_function(array($this, 'close'));
		//$this->create();
		// Debug
		if ($this->debug)  print_r($this->data);
		// Check account
		if ($this->data['account_id'] !== null) {
			// Suspicious : login when logged in
			if (isset($_POST[$this->data['field_submit']]) || isset($_POST[$this->data['field_user']]) || isset($_POST[$this->data['field_pwd']])) {
				$this->error = 'You has been disconnected (re-login)';
				$this->log('DISCONNECTED: re-login (ID = '.$this->data['account_id'].')');
				$this->logout();
				return;
			}
			// User hash : check user agent & ip address
			if ($this->data['user_hash'] !== sha1($this->addr . ':' . $this->agent)) {
				$this->error = 'You has been disconnected (user hash)';
				$this->log('DISCONNECTED: user hash (ID = '.$this->data['account_id'].')');
				$this->logout();
				return;
			}
			// Check user key
			$db = DatabaseConnectorManager::getDatabase('main');
			$id = $db->escapeString($this->data['account_id']);
			$key = $db->escapeString($this->data['account_key']);
			$query = $db->query("SELECT * FROM `".WG::vars('db_prefix')."teammember` WHERE `id` = '$id' AND SHA1(`apikey`) = '$key' LIMIT 1;");
			if ($query->size() !== 1) {
				$this->error = 'You has been disconnected (user key).';
				$this->log('DISCONNECTED: user key (ID = '.$this->data['account_id'].')');
				$this->logout();
				return;
			}
			else {
				// Restore account model
				$this->account = ModelManager::get('TeamMember')->createMoodel($query->current());
				// Protect account password
				//$this->account->password = null;
				// Debug
				if ($this->debug) {
					echo '<p>Session account check : logged as : '.$this->account->login.' ('.$this->data['auth_qop'].')</p>';
				}
			}
		}
		// Login process start
		//else if (isset($_POST[$this->data['field_submit']])) { $this->login(); }
	}*/

	############################################  L O G S

	// TODO Ajouter PHP_AUTH_USER, PHP_AUTH_PW, PHP_AUTH_DIGEST
	public static function syslog() {
		$log = array();
		// Datetime
		$log[] = date('r', $_SERVER['REQUEST_TIME']);
		$log[] = PHP_EOL;
		// Request URI
		if (isset($_SERVER['REQUEST_URI'])) {
			$log[] = 'HTTP/1.0 ';
			$log[] = $_SERVER['REQUEST_METHOD'];
			$log[] = ' ';
			$log[] = $_SERVER['REQUEST_URI'];
			if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_SERVER['REQUEST_URI'] == '/ws.php') {
				$log[] = ' (';
				$log[] = isset($_REQUEST['w']) ? $_REQUEST['w'] : '?';
				$log[] = ')';
			}
			$log[] = PHP_EOL;
		}
		// Referer
		if (isset($_SERVER['HTTP_REFERER'])) {
			$log[] = 'Referer: ';
			$log[] = $_SERVER['HTTP_REFERER'];
			$log[] = PHP_EOL;
		}
		// Remote connection data
		if (isset($_SERVER['REMOTE_ADDR'])) {
			$log[] = 'From: '; 
			$log[] = $_SERVER['REMOTE_ADDR'];
			$log[] = ' (';
			if (!isset($_SESSION['client_host'])) {
				$_SESSION['client_host'] = gethostbyaddr($_SERVER['REMOTE_ADDR']);
			}
			$log[] = $_SESSION['client_host'];
			$log[] = ')';
			$log[] = PHP_EOL;
		}
		// Proxy detection
		$scan_headers = array(
			'HTTP_VIA',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_FORWARDED',
			'HTTP_CLIENT_IP',
			'HTTP_FORWARDED_FOR_IP',
			'VIA',
			'X_FORWARDED_FOR',
			'FORWARDED_FOR',
			'X_FORWARDED',
			'FORWARDED',
			'CLIENT_IP',
			'FORWARDED_FOR_IP',
			'HTTP_PROXY_CONNECTION'
		);
		foreach ($scan_headers as $h) {
			if (isset($_SERVER[$h])) {
				$log[] = 'Via:';
				foreach ($scan_headers as $h) {
					if (isset($_SERVER[$h])) {
						$log[] = ' ';
						$log[] = $h;
						$log[] = '=';
						$log[] = $_SERVER[$h];
					}
				}
				$log[] = PHP_EOL;
				break;
			}
		}
		// User Agent
		$log[] = 'Agent: '; 
		$log[] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
		$log[] = PHP_EOL;
		// Security
		$log[] = 'Security: SSL=';
		$log[] = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'on') || @$_SERVER['SERVER_PORT'] == 443) ? '1' : '0';
		$log[] = ', AES=';
		$log[] = (WG::useAES() ? '1' : '0');
		$log[] = PHP_EOL;
		// User account
		$session = WG::session();
		if ($session && $session->isLogged()) {
			$log[] = 'User: ';
			$log[] = $session->getUser()->get('login');
			$log[] = ' (';
			$log[] = $session->getUser()->get('email');
			$log[] = '), TokenID=';
			$log[] = session_id();
			$log[] = PHP_EOL;
		}
		// Geolocation
		if (isset($_SERVER['GEOIP_COUNTRY_CODE'])) {
			$log[] = 'Location: ';
			$log[] = $_SERVER['GEOIP_COUNTRY_NAME'];
			$log[] = '(';
			$log[] = $_SERVER['GEOIP_COUNTRY_CODE'];
			$log[] = ')';
			if (isset($_SERVER['GEOIP_LATITUDE'])) {
				$log[] = ' [';
				$log[] = $_SERVER['GEOIP_LATITUDE'];
				$log[] = ',';
				$log[] = $_SERVER['GEOIP_LONGITUDE'];
				$log[] = ']';
			}
			$log[] = PHP_EOL;
		}
		$log[] = PHP_EOL;
		return $log;
	}

	public static function syslog_record() {
		file_put_contents(
			WG::base('data/wgcrt.log'), // TODO Utiliser la variable de config
			implode('', self::syslog()),
			FILE_APPEND
		);
	}

	private function log($msg) {
		$log =
			date('r') .
			PHP_EOL .
			$msg .
			PHP_EOL .
			'Session: ' .
			$this->model->get('key') .
			PHP_EOL .
			PHP_EOL;
		$fp = @fopen(self::$logfile, 'a');
		if ($fp) {
			@fwrite($fp, $log);
			@fclose($fp);
		}
	}
	
	private static function debug($log, $session=null, $pad=0) {
		@list($type, $log) = explode(' ', $log, 2);
		if ($session instanceof WGCRT_Session) {
			$log = "<$type session='".$session->getSID()."' $log>\n";
		}
		else if (!is_null($session)) {
			$log = "<$type session='$session' $log>\n";
		}
		else {
			$log = "<$type $log>\n";
		}
		$log = str_repeat(' ', $pad) . $log;
		if (!is_file(self::$debugfile)) {
			@touch(self::$debugfile);
		}
		if (@!file_put_contents(self::$debugfile, $log, FILE_APPEND)) {
				echo "[WGCRT Unable to log in ".self::$debugfile." : $log]";
		}
	}

	############################################  L O G I N   F O R M

	public function loginform() {
		// HTML
		$html = '<form class="wgcrt" id="'.htmlspecialchars($this->data['form_id']).'" action="" method="POST" salt="'.htmlspecialchars($this->data['salt']).'" onsubmit="return WG.security.login(this);" expires="'.($_SERVER['REQUEST_TIME'] + self::$ttl_session).'">';
		$html .= '<div class="container-wgcrt"><div class="wgcrt-realm">'.htmlspecialchars(self::$publicRealm).'</div>';
		if ($this->error !== '') {
			$html .= '<div class="wgcrt-msg">'.htmlspecialchars($this->error).'</div>';
		}
		$html .= '<div><input id="'.$this->data['field_user'].'" type="password" name="'.$this->data['field_user'].'" autocomplete="'.(self::$hideFormFields ? 'off' : 'on').'" placeholder="Login" value="" class="wgcrt-user" />';
		$html .= '<input id="'.$this->data['field_pwd'].'" type="password" name="'.$this->data['field_pwd'].'" autocomplete="off" placeholder="Password" value="" class="wgcrt-pwd" /></div>';
		// RSA+AES
		$html .= '<p><input type="checkbox" id="aes-wgcrt" name="aes-wgcrt" value="on" class="wgcrt-option" /> <label for="aes-wgcrt" class="wgcrt-option" title="Encypt all messages with AES for securized communications.">Crypted channel</label></p>';
		// SSL/TLS
		if (WG::vars('sslurl') != null) {
			$html .= '<p><input type="checkbox" id="ssl-wgcrt" name="ssl-wgcrt" class="wgcrt-option" appurl="'.WG::vars('appurl').'" sslurl="'.WG::vars('sslurl').'" '.(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'checked' : '').'/> <label for="ssl-wgcrt" class="wgcrt-option" title="Cryptographic protocol that provide communication security over the Internet.">TLS/SSL</label></p>';
		}
		// Noscript
		$html .= '<noscript>This form needs JavaScripts</noscript></div></form>';
		// JavaScripts
		$html .= self::js($this->data);
		return $html;
	}

	private static function js($data) {

		$formid = $data['form_id'];
		$submitid = $data['field_submit'];
		$userid = $data['field_user'];
		$pwdid = $data['field_pwd'];

		return <<<_JS
<script type="text/javascript">

// Easing
document.getElementById('{$userid}').focus();

// Virtual keyboard
WG.security.vkb.div = document.getElementById('vkb');
WG.security.vkb.pwd = document.getElementById('{$pwdid}');
WG.security.vkb.pwd.onfocus = function () {
	WG.security.vkb.div.style.display = 'block';
	WG.security.vkb.div.style.top = ($(this).offset().top + 50) + 'px';
};
WG.security.vkb.pwd.onblur = function () {
	WG.security.vkb.div.style.display = 'none';
};
var keys = WG.security.vkb.div.getElementsByTagName('input');
for (var i = 0, j = keys.length; i < j; i++) {
	keys[i].onmouseover = function () {
		WG.security.vkb.key = this.getAttribute('value');
		WG.security.vkb.timer = setTimeout("WG.security.vkb.keypress()", 600);
	};
	keys[i].onmouseout = function () {
		clearTimeout(WG.security.vkb.timer);
	};
}

// SSL Mode
$('#ssl-wgcrt').bind('change', function () {
	if ($(this).is(':checked')) {
		document.location.href = $(this).attr('sslurl');
	}
	else {
		document.location.href = $(this).attr('appurl');
	}
});

// Create submit button
var submit = document.createElement('input');
submit.type = 'submit';
submit.name = '{$submitid}';
submit.className = 'button-yt wgcrt-submit';
document.getElementById('{$formid}').appendChild(submit);

</script>
_JS;
	}

	############################################  P U B L I C    G E T T E R S

	public function get($key) {
		return array_key_exists($key, $this->data) ? $this->data[$key] : null;
	}
	
	public function getSID() {
		if ($this->isDestroyed() && self::$debugMode) {
			print_r(debug_print_backtrace());
		}
		return $this->model->get('id');
	}

	public function getType() {
		return $this->model->get('type');
	}

	public function getKey() {
		return $this->model->get('key');
	}

	public function getLastRequest() {
		return $this->model->get('last_request_dt');
	}

	public function getIPAddr() {
		return $this->model->get('ip_addr');
	}
	
	public function getHostName() {
		return $this->data['hostname'];
	}
	
	public function getUserAgent() {
		return $this->data['user_agent'];
	}

	public function getSecurityTag() {
		return $this->model->get('security');
	}
	
	public function isStarted() {
		return self::$sessionStarted === $this;
	}
	
	public function isDestroyed() {
		return !is_object($this->model);
	}

	############################################  I D E N T I T Y   M A N A G E M E N T

	/**
	 * Associer la session à un compte utilisateur. 
	 * 
	 * @param Moodel $user
	 * @return $this
	 */
	public function login(Moodel $user) {
		
		// Debug
		if (self::$debugMode) {
			$this->debug("login user='$user->login'", $this, 2);
		}
		
		// On change le vrai utilisateur
		$this->model->set('user_true', $user);
		
		// Le champ user_used n'est remplacé que s'il n'existait pas.
		// Le but ici est d'éviter que les sessions de type cli (qui font un login à chaque lancement)
		// n'écrasent systèmatiquement l'user mis avec un sudo.
		if (!$this->model->get('user_used')) {
			$this->model->set('user_used', $user);
		}
		
		// On renvoi la session
		return $this;
		
	}
	
	/**
	 * Déconnecter la session courante.
	 */
	public function logout() {
		// Debug
		if (self::$debugMode) {
			$this->debug("logout", $this, 2);
		}
		// Custom security policy
		if (self::$policy !== null) {
			self::$policy->onLogout($this, 'logout');
		}
		// Clean session
		$this->reset(false);
	}

	public function sudo(Moodel $user) {
		// Debug
		if (self::$debugMode) {
			$this->debug("sudo $user->login", $this, 2);
		}
		$this->model->set('user_used', $user);
		return $this;
	}

	public function unsudo() {
		// Debug
		if (self::$debugMode) {
			$this->debug("unsudo", $this, 2);
		}
		$this->model->set('user_used', $this->model->get('user_true'));
		return $this;
	}

	public function getUser() {
		return $this->model->get('user_used');
	}

	public function getRealUser() {
		return $this->model->get('user_true');
	}

	public function isLogged() {
		return $this->model->get('user_true') != null;
	}

	public function isSudo() {
		if (!$this->isLogged()) {
			return false;
		}
		return $this->model->get('user_true')->get('id') !== $this->model->get('user_used')->get('id');
	}

	// @return string
	public function getUserLogin() {
		$user = $this->model->get('user_used');
		return is_object($user) ? $user->get('login') : '';
	}

	############################################  L I S T E N E R S

	public static function registerGcFunction($callback) {
		self::$gcListeners[] = $callback;
	}
	
	private static function triggerGcEvent($event, WGCRT_Session $session, $throw = false) {
		foreach (self::$gcListeners as $listener) {
			try {
				if ($listener instanceof Closure) {
					$listener($event, $session);
				}
				else {
					call_user_func_array($listener, $session);
				}
			}
			catch (Exception $ex) {
				wgcrt_log_exception($ex);
				if ($throw) {
					throw $ex;
				}
			}
		}
	}

	############################################  M I S C
	
	/**
	 * Initialise la session PHP.
	 * 
	 * @param string|null $session_name Le nom de la session. Laisser à NULL pour avoir la session par défaut.
	 * @throws WGSecurityException Si la function session_start() renvoi une erreur.
	 */
	public static function phpSessionStart($session_name=null) {
		
		// Debug
		if (self::$debugMode) {
			self::debug("php-session action='start'", null, 2);
		}
		
		// PHP sessions config
		@ini_set('url_rewriter.tags', '');
		@ini_set('session.use_trans_sid', 0);
		@session_cache_limiter('private');
		@session_cache_expire(30);
		
		// PHP session name
		if (is_string($session_name)) {
			session_name($session_name);
		}
		
		// Start PHP session
		$start = session_start();
		
		if (!$start) {
			throw new WGSecurityException('Unable to start session');
		}

	}
	
	private function sendNotificationMail() {
		
		// Get hostname
		if (!isset($_SESSION['client_host']) && isset($_SERVER['REMOTE_ADDR'])) {
			$_SESSION['client_host'] = gethostbyaddr($_SERVER['REMOTE_ADDR']);
		}
		
		// Get user account
		$account = $this->getUser();
		if (!$account) return false;
		
		// Mail body
		$mail = array(
			'Dear,',
			'We send you this email after a successful connection to your interface.',
			'',
			"Account ID      : " . htmlspecialchars($account->get('login')) .
				"<br />Ip connection   : " . @htmlspecialchars($_SESSION['client_host']) . " (" . @htmlspecialchars($_SERVER['REMOTE_ADDR']) . ")" .
				"<br />User agent      : " . @html_entity_decode($_SERVER['HTTP_USER_AGENT']) .
				'<br />Connect date    : ' . date('r', $_SERVER['REQUEST_TIME'])  .
				'<br />Application URL : ' . WG::vars('appurl') . " (QoP = ". $this->get('auth_qop') . ")",
			'',
			'This email is intended to alert you to security services you have and better protect them.',
			'To change the settings of these alerts, go to your admin panel.',
			'',
			htmlspecialchars(WG::vars('appOwner'))
		);
		
		// Push mail
		ModelManager::get('EmailCronTask')->new
			->set('to', $account->email)
			->set('from', WG::vars('contact_email'))
			->set('creation', $_SERVER['REQUEST_TIME'])
			->set('title', "Notification of logging into your account: {$account->login}")
			->set('contents', '<html><p>' . implode('</p><p>', $mail) . '</p></html>')
			->save();
		
		return true;		

	}

	public static function minikey($length=10) {
		$dico = 'abcdefghijklmnopqrstuvwxyz';
		//return $dico;
		$r = '';
		while ($length-- > 0) {
			$r .= $dico{rand(0, 25)};
		}
		return $r;
	}

	public static function randomStr($length = 10, $sample = '1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZ-$_()[]+.abcdefghijklmnopqrstuvwxyz') { 
		$s = '';
		for ($i = 0, $l = strlen($sample) - 1; $i < $length; $i++) {
			$s .= $sample[rand(0, $l)];
		}
		return $s;
	}

	private function flood() {
		/*if ($this->data['flood_time'] + 10 < time()) {
			$this->data['flood_time'] = time();
			$this->data['flood_counter'] = 1;
		}
		else {
			if ($this->data['flood_counter'] > 10) {
				$this->error = 'Fatal Error: Flood';
				exit('flood');
			}
			$this->data['flood_counter']++;
		}*/
	}
	
	public static function fixHttpBasicAuthentication() {
		$matches = array();
		if (isset($_SERVER['REMOTE_USER']) && preg_match('/Basic\s+(.*)$/i', $_SERVER['REMOTE_USER'], $matches) > 0) {
			list($name, $pass) = explode(':', base64_decode($matches[1]));
			$_SERVER['PHP_AUTH_USER'] = strip_tags($name);
			$_SERVER['PHP_AUTH_PW'] = strip_tags($pass);
		}
	}
	
	public static function fixHttpDigestAuthentication() {
		$matches = array();
		if (isset($_SERVER['REMOTE_USER']) && preg_match('/Digest\s+(.*)$/i', $_SERVER['REMOTE_USER'], $matches) > 0) {
			$_SERVER['PHP_AUTH_DIGEST'] = $matches[0];
		}
	}
	
	private static function fixPath($path) {
		if (substr($path, 0, 2) == './') {
			return dirname(__FILE__) . substr($path, 1);
		}
		return $path;
	}

	/**
	 * @return WGSecurityPolicy The current custom security policy.
	 */
	public function setCustomSecurityPolicy(WGSecurityPolicy $policy=null) {
		self::$policy = $policy;
	}

	/**
	 * Cette methode sert a autoriser l'acces htaccess a un fichier.
	 * Dans le futur, il faudrait que les sessions soient enregistrees en base de donnee pour:
	 *	- savoir qui est connecte, et combien d'utilisateur sont en ligne
	 *  - pouvoir detecter la fin d'une session, et vider ces authorisations
	 */
	public function allowFileAccess($filename) {
		if (!is_file($filename)) {
			return false;
		}
		$htaccess = dirname($filename) . DIRECTORY_SEPARATOR . '.htaccess';
		$rules = array();
		// Read existing rules
		if (is_file($htaccess)) {
		
		}
		// Write the new rule
		$rule = 'Allow from ' . $_SERVER['REMOTE_ADDR'];
		if (!in_array($rule, $rules)) {
			$rules[] = $rule;
		}
		// Write .htaccess file
		return file_put_contents($htaccess, implode("\n", $rules)) !== false;
	}

	/**
	 * 
	 * @param string $agent
	 * @return false En cas d'erreur, si $agent est invalide
	 * @return string
	 */
	public static function get_browser_versions($agent) {
		$agent = trim(preg_replace('/\(.*\)/', '', $agent));
		if (empty($agent)) return false;
		$agent = preg_split('/\s+/', $agent);
		$software = array();
		foreach ($agent as $k => &$line) {
			list($soft, $version) = explode('/', $line, 2);
			$software[$soft] = $version;
		}
		return $software;
	}
	
	public function __toString() {
		return 'UserSession[sid=' . $this->getSID() . ' type=' . $this->model->get('type') . ' user=' . $this->getUserLogin() . ' data=' . sizeof($this->model->get('data')) . ' key=' . substr($this->model->get('key'), 0, 10) . ']';
	}

}

?>