<?php

class PackageManager extends PHPSubversionServer {

	protected $conf;
	protected $packages;

	public function __construct() {
		// Get configuration from manifest files
		$this->conf = WG::vars('workspace');
		// Construct PHPSubversionServer
		parent::__construct(
			$this->conf['root_folder'],
			$this->conf['meta_folder'],
			new SoHoACLManager(),
			new SoHoAuthManager()
		);
		// Initialize package data
		$this->initPackages();
		// Error reporting level
		error_reporting(WG::vars('dev_mode') === true ? E_ALL : 0);
	}

	protected function initPackages() {
		$this->packages = array();
		if ($handle = opendir($this->conf['root_folder'])) {
			while (false !== ($file = readdir($handle))) {
				if ($file == '.' || $file == '..') continue;
				if (!is_dir($this->conf['root_folder'].'/'.$file)) continue;
				$this->packages[$file] = null;
			}
		}
		else {
			throw new Exception('invalid workspace folder');
		}
	}

	public function getPackageState($name, $seekFiles=true, $useCache=true) {
		// Le package n'existe pas
		if (!array_key_exists($name, $this->packages)) {
			return null;
		}
		$tmp = $this->packages[$name];
		// Le package a d�j� �t� charg�
		if (is_object($tmp) && $useCache) {
			if ($tmp->files === null && $seekFiles) {
				// Si le package d�j� charg� ne contient pas les fichiers,
				// on laisse passer jusqu'� la fin de la fonction pour
				// qu'ils soient charg�s.
			}
			else {
				return $tmp;
			}
		}
		$tmp = null;
		// Restauration du cache (s'il existe)
		$cache = PackageState::getCache($name);
		// Cr�ation du package du package
		if (!$useCache || !$cache) {
			$tmp = PackageState::createFromFolder($this->conf['root_folder'].'/'.$name, $seekFiles);
		}
		// Cache expir�
		/*else if (time() - $cache->get('update') > $ttl) {
			$tmp = PackageState::createFromFolder($this->conf['root_folder'].'/'.$name, $seekFiles);
		}*/
		// Chargement du cache
		else {
			$tmp = PackageState::restoreFromCache($name, $cache);
		}
		// Sauvegarde du package en interne
		if (is_object($tmp)) {
			$this->packages[$name] = $tmp;
		}
		return $tmp;
	}

	/**
	 * Renvoi un tableau contenant des infos publiques sur le repository,
	 * ainsi que la liste des packages disponibles.
	 *
	 * Note: le contenu de la liste est renvoy�e directement dans le webservice plug-list,
	 * il faut donc faire attention de ne pas envoyer trop d'info (�conomie de bande pasante).
	 */
	public function getList() {
		$r = array(
			'repository-name' => $this->conf['name'],
			'repository-url' => WG::vars('appurl'),
			'packages' => array()
		);
		foreach ($this->packages as $p => $d) {
			if ($d == null) {
				$d = $this->getPackageState($p, true);
			}
			if ($d == null) {
				continue;
			}
			// Check ACL : read
			if (!$this->aclMgr->allow(ACLAction::R_READ, '/'.$d->package.'/', WG::user()->login)) {
				continue;
			}
			$access = 'r';
			// Check ACL : write
			if ($this->aclMgr->allow(ACLAction::R_WRITE, '/'.$d->package.'/', WG::user()->login)) {
				$access .= '+w';
			}
			$r['packages'][] = array(
				'id' => $d->id,
				'name' => $d->name,
				'package' => $d->package,
				'description' => $d->description,
				'version' => $d->version,
				'lastchange' => date('Y/m/d H:i', $d->lastchange),
				'access' => $access,
				'status' => isset($d->properties['project.status']) ? $d->properties['project.status'] : null
			);
		}
		return $r;
	}

	public function handle_list() {
		echo json_encode($this->getList());
		return true;
	}

	public function handle_commit() {
		// Check request
		if (!isset($_REQUEST['package']) || !isset($_REQUEST['message']) || !isset($_FILES['data'])) {
			$this->error('Bad Request', 400);
			return false;
		}
		// Find package
		$package = $this->getPackageState($_REQUEST['package'], true);
		if (!$package) {
			$this->error('Package Not Found', 404);
			return false;
		}
		// Open archive
		$zip = new ZipArchive();
		$open = $zip->open($_FILES['data']['tmp_name']);
		if ($open !== true) {
			$this->error('Internal Error: '.ziplib_error_name($open), 500);
			return false;
		}
		// Commit ID
		$commitId = sha1(time() . ':' . rand(0, 99999999999));
		// Fetch files (indexes)
		for ($i = 0; $i < $zip->numFiles; $i++) {
			// Get index
			$index = $zip->statIndex($i);
			$rpath = '/' . $_REQUEST['package'] . '/' . $index['name'];
			// Open stream to index
			$fp = $zip->getStream($index['name']);
			if (!$fp) {
				$this->error('IO Internal Error', 500);
				return false;
			}
			// Read stream
			$contents = '';
			while (!feof($fp)) {
				$contents .= fread($fp, 2);
			}
			fclose($fp);
			// Execute file_put_contents
			$write = $this->file_put_contents(
				$rpath,
				$contents,
				WG::user()->login,
				trim($_REQUEST['message'])
			);
			// Check result
			if ($write !== ReturnCodeEnum::E_OK) {
				if ($write === ReturnCodeEnum::E_NOT_ALLOWED) {
					$this->error("Forbidden Write: $rpath", 403);
				}
				else {
					$this->error(ReturnCodeEnum::nameOf($write) . ': '.$index['name'], 500);
				}
				return false;
			}
			// Fix mtime
			// TODO
		}
		// Return commit validation
		echo json_encode(array(
			'commit_id' => $commitId,
			'package' => $_REQUEST['package'],
			'user' => WG::user()->login,
			'files' => $i,
			'delete' => $d,
			'request_time' => $_SERVER['REQUEST_TIME'],
			'timezone' => date_default_timezone_get()
		));
		// Refresh
		$this->getPackageState($_REQUEST['package'], true, false);
		return true;
	}

	public function handle_create() {
		// Check request
		if (!isset($_REQUEST['package']) || !isset($_FILES['data'])) {
			$this->error('Bad Request', 400);
			return false;
		}
		// Check the package name
		$package = trim($_REQUEST['package']);
		if (strpos($package, '/') !== false ||
			strpos($package, ':') !== false ||
			strpos($package, '?') !== false ||
			strpos($package, '*') !== false ||
			strpos($package, '|') !== false ||
			strpos($package, '<') !== false ||
			strpos($package, '>') !== false ||
			strpos($package, '\\') !== false) {
			$this->error('Invalid Package Name', 400);
			return false;
		}
		// ACL
		if (!$this->aclMgr->allow(ACLAction::R_WRITE, '/', WG::user()->login)) {
			$this->error('Unauthorized', 401);
			return false;
		}
		// Check if package allready exists
		if (is_dir($this->rootDir . '/' . $package)) {
			$this->error('Package Allready Exists', 400);
			return false;
		}
		// Open archive
		$zip = new ZipArchive();
		$open = $zip->open($_FILES['data']['tmp_name']);
		if ($open !== true) {
			$this->error('Internal Error: '.ziplib_error_name($open), 500);
			return false;
		}
		// Create directory
		// Note: � partir d'ici, il faut supprimer les r�pertoires en cas d'erreur.
		if (!mkdir($this->rootDir . '/' . $package)) {
			$this->error('Internal Error: unable to create project folder', 500);
			return false;
		}
		// Commit ID
		$commitId = sha1(time() . ':' . rand(0, 99999999999));
		// Flag d'erreur
		$error = false;
		// Fetch files (indexes)
		for ($i = 0; $i < $zip->numFiles; $i++) {
			// Get index
			$index = $zip->statIndex($i);
			$rpath = $this->rootDir . '/' . $package . '/' . $index['name'];
			// Create folders
			if (substr($index['name'], -1) === '/') {
				if (!mkdir($rpath, 0777, true)) {
					
				}
				continue;
			}
			// Open stream to index
			$fp = $zip->getStream($index['name']);
			if (!$fp) {
				// On renvoi une erreur au client
				$this->error('IO Internal Error', 500);
				// On indique qu'on a provoqu� une erreur
				$error = true;
				// Et on arr�te de boucler sur les indexes
				break;
			}
			// Write stream
			$write = $this->file_write_stream(
				$package . '/' . $index['name'],
				$fp,
				WG::user()->login,
				"$commitId"
			);
			fclose($fp);
			// Check result
			if ($write !== ReturnCodeEnum::E_OK) {
				// On renvoi une erreur au client
				if ($write === ReturnCodeEnum::E_NOT_ALLOWED) {
					$this->error("Forbidden Write: $rpath", 403);
				}
				else {
					$this->error(ReturnCodeEnum::nameOf($write) . ': '.$index['name'], 500);
				}
				// On indique qu'on a provoqu� une erreur
				$error = true;
				// Et on arr�te de boucler
				break;
			}
			// Checksum
			/*$oldCrc = $stat['crc'];
			$newCrc = hexdec(hash_file('crc32b', $rpath));
			// Have to test both cases as the unsigned CRC from within the zip might
			// appear negative as a signed int.
			if ($newCrc !== $oldCrc && ($oldCrc + 4294967296) !== $newCrc) {
				
			}*/
			// Fix mtime
			$c = $zip->getCommentIndex($i);
			if (is_numeric($c)) {
				$n = $zip->statIndex($i);
				// Also : $n['mtime']
				if (!touch($rpath, intval($c))) {
					// On renvoi une erreur au client
					$this->error('IO Internal Error: TimeFix', 500);
					// On indique qu'on a provoqu� une erreur
					$error = true;
					// Et on arr�te de boucler sur les indexes
					break;
				}
			}
			else {
				// On renvoi une erreur au client
				$this->error('Invalid File TimeFix', 400);
				// On indique qu'on a provoqu� une erreur
				$error = true;
				// Et on arr�te de boucler sur les indexes
				break;
			}
		}
		// En cas d'erreur, on doit supprimer les fichiers et les dossiers
		// qui ont �t� rajout�s. En fait, on va supprimer tout le r�pertoire du projet
		// et renvoyer FALSE.
		// Le code d'erreur a normalement d�j� �t� envoy� au client.
		if ($error) {
			// Suppression recursive
			rrmdir($this->rootDir . '/' . $package);
			// Retour d'erreur
			return false;
		}
		// Return commit validation
		echo json_encode(array(
			'commit_id' => $commitId,
			'package' => $package,
			'user' => WG::user()->login,
			'files' => $i,
			'request_time' => $_SERVER['REQUEST_TIME'],
			'timezone' => date_default_timezone_get()
		));
		// Refresh package
		$this->getPackageState($package, true, false);
		return true;
	}

	public function handle_refresh() {
		// Check request
		if (!isset($_REQUEST['package'])) {
			$this->error('Bad Request', 400);
			return false;
		}
		// Find & refresh package
		$package = $this->getPackageState($_REQUEST['package'], true, false);
		if (!$package) {
			$this->error('Package Not Found', 404);
			return false;
		}
		// ACL
		if (!$this->aclMgr->allow(ACLAction::R_DIRLIST, '/'.$_REQUEST['package'].'/', WG::user()->login)) {
			$this->error('Unauthorized', 401);
			return false;
		}
		// OK
		echo '{"refresh":"done"}';
		return true;
	}

	public function handle_clone() {
		// Check request
		if (!isset($_REQUEST['package'])) {
			$this->error('Bad Request', 400);
			return false;
		}
		// Find package
		$package = $this->getPackageState($_REQUEST['package']);
		if (!$package) {
			$this->error('Package Not Found', 404);
			return false;
		}
		// ACL
		if (!$this->aclMgr->allow(ACLAction::R_READ, '/'.$package->package.'/', WG::user()->login)) {
			$this->error('Unauthorized', 401);
			return false;
		}
		// Config
		ignore_user_abort(true);
		set_time_limit(0);
		// Cr�ation du fichier ZIP temporaire
		$filename = tempnam(WG::base('tmp'), 'zip');
		$zip = new ZipArchive();
		if ($zip->open($filename, ZIPARCHIVE::CREATE) !== true) {
			$this->error('Temporary IO Error [0x1]', 500);
			return false;
		}
		// Fonction pour ajouter des fichiers dans l'archive
		function addFolderInArchive($zip, $dir, $base='/') {
			if ($handle = opendir($dir)) {
				while (false !== ($file = readdir($handle))) {
					if ($file == '.' || $file == '..') {
						continue;
					}
					$path = $dir.'/'.$file;
					if (is_dir($path)) {
						$zip->addEmptyDir($base . $file);
						addFolderInArchive($zip, $path, $base . $file . '/');
					}
					else {
						$zip->addFile($path, $base . $file);
						$zip->setCommentName($base . $file, filemtime($path));
					}
				}
			}
		}
		// Ajouter les fichiers dans l'archive
		addFolderInArchive($zip, $this->rootDir . '/' . $package->package);
		// Erreur
		if ($zip->numFiles == 0) {
			$this->error('Temporary IO Error [0x2]', 500);
			return false;
		}
		// Commentaire du zip
		$zip->setArchiveComment('wg.plug-get');
		// Fermeture du fichier temporaire
		$zip->close();
		// Ouverture du fichier temporaire
		$fp = fopen($filename, 'rb');
		if (!$fp) {
			// Suppression du fichier temporaire
			unlink($filename);
			$this->error('Temporary IO Error [0x3]', 500);
			return false;
		}
		// Headers
		$file = basename($filename);
		header("Content-Type: application/force-download; name=\"$file\"", true);
		header("Content-Transfer-Encoding: binary", true);
		header("Content-Disposition: attachment; filename=\"$file\"", true);
		header("Expires: 0", true);
		header("Cache-Control: no-cache, must-revalidate", true);
		header("Pragma: no-cache", true);
		header("Content-Length: " . filesize($filename), true);
		// Envoi du fichier
		ob_end_clean();
		fpassthru($fp);
		fclose($fp);
		// Suppression du fichier temporaire
		@unlink($filename);
		return true;
	}

	public function handle_versions() {
		// Check request
		if (!isset($_REQUEST['path'])) {
			$this->error('Bad Request', 400);
			return false;
		}
		// Clean path
		$path = cleanpath($_REQUEST['path']);
		// Get versions
		$versions = $this->versions($path);
		// Display versions
		if (is_array($versions)) {
			$r = array();
			foreach ($versions as $v) {
				$r[] = array(
					'user' => $v['u'],
					'size' => $v['s'],
					'date' => $v['c'],
					'msg' => $v['m']
				);
			}
			echo json_encode($r);
			return true;
		}
		// Il n'y a pas de versions, juste le fichier physique
		else if (is_file($this->rootDir . $path)) {
			echo json_encode(array(array(
				'user' => '-',
				'size' => sizeof($this->rootDir . $path),
				'date' => filemtime($this->rootDir . $path),
				'msg' => 'Original file'
			)));
			return true;
		}
		else {
			$this->error('Path Not Found', 404);
			return false;
		}
	}

	public function handle_state() {
		// Check request
		if (!isset($_REQUEST['package'])) {
			$this->error('Bad Request', 400);
			return false;
		}
		// Find package
		$package = $this->getPackageState($_REQUEST['package'], true);
		if (!$package) {
			$this->error('Package Not Found', 404);
			return false;
		}
		// Return as JSON
		echo json_encode($package->asArray());
		return true;
	}

	public function handle_delete() {
		// Check request
		if (!isset($_REQUEST['paths'])) {
			$this->error('Bad Request', 400);
			return false;
		}
		//
		echo "ok du serveur";
	}

}

class SoHoACLManager implements SubversionServerACLManager {

	/**
	 * \brief Test si une action est authoris�e pour l'utilisateur.
	 *
	 * @param int $action Le code d'action.
	 * @param string $path Le chemin vers la cible.
	 * @param string $username Le nom de l'utilisateur qui effectue l'action.
	 * @return boolean
	 */
	public function allow($action, $path, $username) {
		//echo "[".ACLAction::nameOf($action)." $username $path]";
		switch ($action) {
			case ACLAction::R_DIRLIST :
				return true;
				break;
			case ACLAction::R_READ :
				return true;
				break;
			case ACLAction::R_WRITE :
				return $username === 'remi';
				break;
		}
		return false;
	}

}

class SoHoAuthManager implements SubversionServerAuthManager {

	/**
	 * \brief Test l'authentification de l'utilisateur.
	 *
	 * @param string $username Le nom d'utilisateur. 
	 * @param string $password Le mot de passe.
	 * @return boolean
	 */
	public function login($username, $password) {
		return false;
	}

	/**
	 * \brief Renvoi le nom de l'utilisateur de la session.
	 *
	 * Si la session n'est pas authentifi�e, cette fonction
	 * renvoi NULL.
	 *
	 * @return string Si la session est authentifi�e.
	 * @return null Si la session n'est pas authentifi�e.
	 */
	public function username() {
		if (WG::user() != null) {
			return WG::user()->login;
		}
		return null;
	}

}

/**
 * \brief Etat d'un package.
 */
class PackageState {

	/**
	 * \brief Les donn�es de ce package.
	 *
	 * @var array
	 */
	private $nfo;

	/**
	 * \brief Constructeur de la classe.
	 *
	 * Constructeur priv�, pour que cet objet ne soit cr�� qu'� partir
	 * des m�thodes createFromFolder() et getCache().
	 */
	private function __construct() { }

	/**
	 * \brief Acc�s rapide aux donn�es du package.
	 *
	 * M�thode magique pour acc�der simplement aux donn�es du package.
	 */
	public function __get($name) {
		return isset($this->nfo[$name]) ? $this->nfo[$name] : null;
	}

	/**
	 * \brief Renvoyer toutes les info de cet �tat dans un tableau.
	 *
	 * Cette m�thode permet de synth�tiser l'essentiel des donn�es
	 * de cet �tat dans un tableau.
	 *
	 * @return array
	 */
	public function asArray() {
		$name = WG::vars('workspace');
		$name = $name['name'];
		$r = array(
			'repository-name' => $name,
			'repository-url' => WG::vars('appurl'),
			'package' => $this->nfo['package'],
			'server_time' => time(),
			'lastchange' => $this->nfo['lastchange'],
			'filescount' => 0
		);
		if (isset($this->nfo['files'])) {
			$r['files'] = $this->nfo['files'];
			$r['filescount'] = sizeof($this->nfo['files']);
		}
		return $r;
	}

	/**
	 * \brief Cr�er un PackageState � partir du dossier donn�.
	 *
	 * Ce processus va lire le fichier build.xml pour en r�cup�rer les info
	 * (version, properties, et la liste des targets).
	 *
	 * @param string $path Chemin vers le r�pertoire cible.
	 * @param boolean $seekFiles Si vaut TRUE, alors le d�tail des fichiers (cr��
	 *  gr�ce � la m�thode seekDirectoryData) sera int�gr� � l'�tat du package.
	 * @param boolean $save Si vaut TRUE, alors le PackageState sera enregistr�
	 *  en cache.
	 * @return int En cas d'erreur :
	 *  <ul>
	 *   <li>-1 : Le r�pertoire $path n'est pas un dossier.
	 *   <li>-2 : Le r�pertoire $path ne contient pas de fichier nomm� 'build.xml',
	 *    il n'est donc pas consid�r� comme un package.
	 *   <li>-3 : Erreur renvoy�e par OctoPHPus lors de la lecture du fichier de build.
	 *   <li>-4 : Erreur renvoy�e par la m�thode seekDirectoryData().
	 *  </ul>
	 * @return PackageState L�tat actuel du package.
	 */
	public static function createFromFolder($path, $seekFiles=true, $save=true) {
		if (!is_dir($path)) return -1;
		$state = new PackageState();
		$package = basename($path);
		// Basic informations
		$state->nfo = array(
			'id' => md5($path),
			'name' => '',
			'path' => realpath($path),
			'package' => $package,
			'tokens' => explode('.', $package),
			'buildfile' => realpath($path).'/build.xml',
			'version' => '',
			'description' => '',
			'lastchange' => -1
		);
		// Read build.xml
		if (is_file($path.'/build.xml')) {
			try {
				WG::lib('octophpus-current.php');
				$o = new OctoPHPus();
				$state->nfo['targets'] = $o->getTargetsInBuildXML($path.'/build.xml');
				$state->nfo['name'] = $o->getProjectName();
				$prop = $o->getProperties();
				$state->nfo['properties'] = $prop;
			}
			catch (Exception $ex) {
				return -2;
			}
			if (isset($prop['project.version'])) {
				$state->nfo['version'] = $prop['project.version'];
			}
			if (isset($prop['project.description'])) {
				$state->nfo['description'] = $prop['project.description'];
			}
			unset($o, $prop);
		}
		if (!$seekFiles) return $state;
		// Get directory data
		$data = self::seekDirectoryData($path);
		if (!is_array($data)) {
			return -4;
		}
		$state->nfo['files'] = $data[0];
		$state->nfo['lastchange'] = $data[1];
		// Save in cache
		if ($save) {
			self::saveInCache($state);
		}
		return $state;
	}

	/**
	 * \brief Renvoi le model du cache pour un package donn�.
	 *
	 * @param string $packageName Le nom du package.
	 * @return null Si le cache n'a pu �tre r�cup�r�.
	 * @return Moodel Le cache.
	 */
	public static function getCache($packageName) {
		$store = ModelManager::get('Store')->get(array(
			'name' => 'plug-cache',
			'item' => $packageName
		));
		if (sizeof($store) !== 1) {
			return null;
		}
		return $store[0];
	}

	/**
	 * \brief Restaurer les donn�es en cache d'un package.
	 *
	 * @param string $packageName Le nom du package.
	 * @param Moodel|null $cache Le cache � utiliser. Par d�faut, et si $cache vaut NULL,
	 *  le cache est automatiquement r�cup�r�. Le fait de pouvoir sp�cifier le cache
	 *  permet d'am�liorer les performances quand l'instance du cache a d�j� �t� r�cup�r�e
	 *  avant l'appel de cette m�thode.
	 * @return null Si aucun cache n'existe.
	 * @return PackageState L'�tat actuel du package enregistr� dans le cache.
	 */
	public static function restoreFromCache($packageName, $cache=null) {
		// Recup�ration du cache
		if (!$cache) {
			$cache = self::getCache($packageName);
		}
		// Impossible de charger ce cache
		if (!$cache) {
			return null;
		}
		// Restauration du cache
		return $cache->data;
	}

	/**
	 * \brief Enregistre l'�tat d'un package dans le cache.
	 *
	 * @param PackageState $state L'�tat du package.
	 * @param Moodel|null $cache Le cache � utiliser. Par d�faut, et si $cache vaut NULL,
	 *  le cache est automatiquement r�cup�r�. Le fait de pouvoir sp�cifier le cache
	 *  permet d'am�liorer les performances quand l'instance du cache a d�j� �t� r�cup�r�e
	 *  avant l'appel de cette m�thode.
	 * @return boolean Renvoi TRUE si l'enregistrement du cache est r�ussie, FALSE sinon.
	 */
	public static function saveInCache(PackageState $state, $cache=null) {
		// Recup�ration du cache
		if (!$cache) {
			$cache = self::getCache($state->package);
		}
		// Cr�ation du cache s'il n'existe pas
		if (!$cache) {
			$cache = ModelManager::get('Store')->new
				->set('name', 'plug-cache')
				->set('item', $state->package);
		}
		// Modification du contenu du cache
		$cache
			->set('update', time())
			->set('data', $state);
		// Enregistrement du cache
		return $cache->save();

	}

	/**
	 * \brief Rechercher toutes les donn�es sur un r�pertoire.
	 *
	 * Cette m�thode interne permet de renvoyer un tableau contenant toutes
	 * les informations sur les fichiers pr�sents dans un r�pertoire.
	 * 
	 * Cette m�thode renvoi un tableau � 3 indices, respectivement :
	 * <ul>
	 *  <li>0 : array, avec en indique le chemin vers le fichier, et en valeur
	 *   un tableau � deux indices ('t', le type ('d' pour dir et 'f' pour fichier),
	 *   's' la taille du r�pertoire/fichier, et pour les fichiers uniquement:
	 *   'c' pour le ctime et 'm' pour le mtime.
	 *  <li>1 : int, le timestamp de la derni�re modification du r�pertoire.
	 *  <li>2 : int, la taille totale du r�pertoire.
	 * </ul>
	 *
	 * Si le r�pertoire $dir n'est pas lisible, la m�thode renvoi NULL.
	 *
	 * @param string $dir Chemin vers le r�pertoire.
	 * @param string $path Utilis� en interne, ne pas s'en soucier.
	 * @param int $time Utilis� en interne, ne pas s'en soucier.
	 * @param int $size Utilis� en interne, ne pas s'en soucier.
	 * @return array
	 */
	protected static function seekDirectoryData($dir, $path='', $time=0, $size=0) {
		if ($handle = opendir($dir)) {
			$r = array();
			while (false !== ($file = readdir($handle))) {
				if ($file == '.' || $file == '..') {
					continue;
				}
				$tmp = "$dir/$file";
				// Dans le cas d'un sous-r�pertoire
				if (is_dir($tmp)) {
					// On enregistre le sous-r�pertoire
					$r[$path . $file] = array(
						't' => 'd',
						's' => 0
					);
					// On recup�re les donn�es du sous-r�pertoire
					$tmp = self::seekDirectoryData($tmp, $path . $file . '/');
					// Si le dossier est lisible
					if (is_array($tmp)) {
						// On enregistre la derni�re modification du r�pertoire
						$time = max($tmp[1], $time);
						// On enregistre la taille du sous-r�pertoire
						$r[$path . $file]['s'] = $tmp[2];
						// On incr�mente le taille du r�pertoire parent
						$size += $tmp[2];
						// On ajoute les fichiers du sous-r�pertoire
						$r = array_merge($r, $tmp[0]);
					}
					unset($tmp);
				}
				// Dans le cas d'un fichier
				else {
					// On recup�re la taille du fichier
					$s = filesize($tmp);
					// On enregistre le fichier
					$r[$path . $file] = array(
						't' => 'f',
						's' => $s,
						'c' => filectime($tmp),
						'm' => filemtime($tmp)
					);
					// On enregistre la derni�re modification du fichier
					$time = max($time, filemtime($tmp));
					// On incr�mente le taille du r�pertoire
					$size += $s;
				}
			}
			closedir($handle);
			return array($r, $time, $size);
		}
		return null;
	}

}

/*

require_once '../starter.php';
require_once 'PHPSubversionServer.lib.php';
require_once 'octophpus-current.php';

header('Content-type: text/plain');

$mgr = new PackageManager();

$list = $mgr->getList();

echo "Repository: ".$list['repository-name'];

echo "\n\nFolders:";

foreach ($mgr->ls('/') as $file => $isdir) {
	if ($isdir) echo "\n  $file";
}

echo "\n\nPackages:";

foreach ($list['packages'] as $package) {
	echo "\n  {$package['package']}";
}

echo "\n\nTargets evolya.phpsubversionserver:";

$package = $mgr->getPackageState('evolya.phpsubversionserver', true);

foreach ($package->targets as $target => $desc) {
	echo "\n  $target: $desc";
}

echo "\n\nUpdate filesystem evolya.phpsubversionserver:";

$update = $mgr->update('/evolya.phpsubversionserver/', true, false);

if (is_array($update)) {
	echo " ".sizeof($update)." change(s)";
	foreach ($update as $file) {
		echo "\n  $file";
	}
}
else {
	echo " error (".ReturnCodeEnum::error_name($update).")";
}

*/

?>
