<?php

//require_once dirname(__FILE__) . '/../../starter.php';

$modelUserSession = new MoodelStruct(DatabaseConnectorManager::getDatabase('main'), 'UserSession', array(

	'id' => 'int:auto_increment,primary_key',
	'type' => 'char[10]',
	'key' => 'char[40]', // sha1

	'user_true' => 'int:foreign_key[TeamMember=id]',
	'user_used' => 'int:foreign_key[TeamMember=id]', // modifié par le sudo

	'ip_addr' => 'char[40]',
	'security' => 'char[10]',

	'start_dt' => 'datetime',
	'last_request_dt' => 'datetime',

	'data' => 'serial'


));

/*$modelUserSession->drop();
$modelUserSession->createTable();*/

/*
final class UserSession {

	protected $model = null;

	### Init

	protected static $initialized = false;
	protected static $struct;

	protected function __construct(Moodel $model) {
		$this->model = $model;
	}

	public static function init() {
		if (!self::$initialized) {
			self::$initialized = true;
			self::$struct = ModelManager::get('UserSession');
			self::gc();
			self::shutdown();
		}
	}

	### Public getters

	public function getSID() {
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

	public function getSecurityTag() {
		return $this->model->get('security');
	}

	### Static getters

	protected static $cache = array();

	public static function createSessions($array) {
		if (!is_array($array)) {
			throw new Exception('Invalid session array');
		}
		$sessions = array();
		foreach ($array as $model) {
			$id = $model->get('key');
			if (isset(self::$cache[$id])) {
				$sessions[] = self::$cache[$id];
			}
			else {
				self::$cache[$id] = new UserSession($model);
				$sessions[] = self::$cache[$id];
			}
		}
		return $sessions;
	}

	// @return UserSession|null
	public static function getById($id) {
		$model = self::$struct->getById($id, 1);
		if ($model) {
			if (isset(self::$cache[$model->get('key')])) {
				return self::$cache[$model->get('key')];
			}
			$session = new UserSession($model);
			self::$cache[$model->get('key')] = $session;
			return $session;
		}
		return null;
	}

	// @return UserSession[]
	public static function getSessions() {
		if (!self::$initialized) {
			throw new Exception('UserSession class not initialized');
		}
		// In database
		$sessions = self::createSessions(self::$struct->all());
		// In cache
		foreach (self::$cache as $session) {
			$found = false;
			foreach ($sessions as $s) {
				if ($s->getKey() == $session->getKey()) {
					$found = true;
					break;
				}
			}
			if (!$found) {
				$sessions[] = $session;
			}
		}
		return $sessions;	
	}

	// @return UserSession[]
	public static function getUserSessions(Moodel $user) {
		if (!self::$initialized) {
			throw new Exception('UserSession class not initialized');
		}
		return self::createSessions(self::$struct->get(array('user_true' => $user->get('id'))));
	}

	// @return null|UserSession
	public static function getSession($type=null, $key=null) {

		if (!self::$initialized) {
			throw new Exception('UserSession class not initialized');
		}

		$type = is_string($type) ? $type : PHP_SAPI;
		$key = is_string($key) ? $key : self::guessSessionKey($type);

		if (isset(self::$cache[$key])) {
			return self::$cache[$key];
		}

		$struct = ModelManager::get('UserSession');

		$sessions = $struct->get(array(
			'type' => $type,
			'key' => $key
		));

		if (sizeof($sessions) > 0) {

			$session = new UserSession($sessions[0]);

			self::$cache[$key] = $session;

			return $session;

		}

		$model = $struct->new
			->set('type', $type)
			->set('key', $key)
			->set('start_dt', $_SERVER['REQUEST_TIME'])
			->set('data', array());

		$session = new UserSession($model);

		self::$cache[$key] = $session;

		$model->save();

		return $session;

	}

	### Key

	public static $allowIPChange = false;
	public static $allowAgentChange = false;

	public static function guessSessionKey($type) {

		if (!isset($_SERVER)) {
			throw new Exception('Unable to guess session key: no global var $_SERVER');
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

		if (sizeof($key) < 1) {
			throw new Exception('Unable to guess session key: not enought key token');
		}

		$key[] = $type;

		return sha1(implode(':', $key));

	}

	### Identity

	public function login(Moodel $user) {
		$this->model->set('user_true', $user);
		if (!$this->model->get('user_used')) {
			$this->model->set('user_used', $user);
		}
		return $this;
	}

	public function sudo(Moodel $user) {
		$this->model->set('user_used', $user);
		return $this;
	}

	public function unsudo() {
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

	### Data

	protected $data = array();

	public function __isset($varname) {
		return isset($this->data[$varname]);
	}

	public function get($varname) {
		return array_key_exists($varname, $this->data) ? $this->data[$varname] : null;
	}

	public function set($varname, $value) {
		$this->data[$varname] = $value;
	}

	public function __unset($varname) {
		unset($this->data[$varname]);
	}

	public function data() {
		return $this->data;
	}

	### Garbage collector

	protected static $ttl_session = 3600; // seconds
	protected static $ttl_chuser  = 900; // seconds

	public function destroy() {
		unset(self::$cache[$this->model->get('key')]);
		$this->model->delete();
	}

	protected static function shutdown() {
		if (!self::$initialized) {
			throw new Exception('UserSession class not initialized');
		}
		register_shutdown_function('UserSession::write');
	}

	public static function write() {
		if (!self::$initialized) {
			throw new Exception('UserSession class not initialized');
		}
		foreach (self::$cache as $key => $session) {
			$r = $session->model
				->set('data', $session->data)
				->save();
			//echo "[WRITE $r $session]\n";
		}
	}

	public static function gc() {
		if (!self::$initialized) {
			throw new Exception('UserSession class not initialized');
		}
		$db = self::$struct->_db;
		$sql = 'DELETE FROM `'.$db->getDatabaseName().'`.`'.$db->getPrefix().'usersession` WHERE `last_request_dt` + ' . self::$ttl_session . ' < ' . $_SERVER['REQUEST_TIME'];
		$c = $db->query($sql);
		//echo "[GC = $c session(s) deleted]\n";
	}

	### Misc

	public function update() {
		$https = (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
			 (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on');
		$host = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'localhost';
		$this->model
			->set('security', $https ? 'SSL' : '-')
			->set('ip_addr', $host)
			->set('last_request_dt', $_SERVER['REQUEST_TIME']);
	}

	function __toString() {
		return 'UserSession[type=' . $this->model->get('type') . ' user=' . $this->getUserLogin() . ' data=' . sizeof($this->model->get('data')) . ' key=' . $this->model->get('key') . ']';
	}

}*/

?>
