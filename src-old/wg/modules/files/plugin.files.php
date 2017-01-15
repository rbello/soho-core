<?php

/**
 * 
 * Gestion du service de fichier
 * 
 */
class Soho_Plugin_Files extends Soho_PluginBase {

	/**
	 * Instance du gestionnaire d'extension du système de fichier.
	 * @var Soho_Plugin_ExFs
	 */
	protected $exfs = null;	

	/**
	 * Chemin vers le répertoire racine du système de fichier interne au plugin.
	 * Si elle vaut null après l'initialisation du plugin, cela signifie que le plugin n'est pas actif
	 * car la configuration n'a pas spécifié de répertoire racine.
	 * @var string
	 */
	protected $rootDir = null;
	
	/**
	 * Cache de la méthode Soho_Plugin_Files::foldersize()
	 * @var int[]
	 */
	protected static $cache_foldersize = array();

	/**
	 * Constructeur de la classe.
	 */
	public function __construct() {

		// On renseigne le nom du plugin
		parent::__construct('files');
	
		// On rends disponible l'API de cette classe directement
		$this->api['files'] = $this;
		
		// TODO Déclarer les commandes de l'API CLI pour ce plugin
	
	}
	
	/**
	 * (non-PHPdoc)
	 * @see Soho_Plugin::init()
	 */
	public function init() {
		
		// On intialise le chemin vers le répertoire root
		$rootDir = WG::vars('files_folder');
		
		// Si aucun répertoire n'est indiqué, le plugin file n'est pas activé
		if (!is_string($rootDir)) {
			return;
		}
		
		// On essaye de résoudre le chemin vers le répertoire racine
		$rootDir = realpath($rootDir);
		
		// On vérifie que le répertoire existe bien et qu'il soit lisible
		if ($rootDir != false && is_dir($rootDir) && is_readable($rootDir)) {
			// On sauvegarde le chemin
			$this->rootDir = $rootDir;
		}
		// Sinon on lève une exception
		else {
			throw new WGFilesPluginException("Invalid root directory for plugin Files");
		}
		
		// On recupère l'API d'exfs
		$this->exfs = WG::exfs();
		
		// Ajouter les données de pathinfo()
		define('PATHINFO_DETAILS', 2);
		
		// Ajouter les temps (A)ccess, (M)odification et (C)reation
		define('PATHINFO_TIMES', 4);
		
		// Calculer la taille des répertoires de manière récursive
		define('PATHINFO_RECURSIVE', 8);
		
		// Intégrer les données EXFS du noeud
		define('PATHINFO_EXFS', 16);
		
		// Toutes les options
		define('PATHINFO_ALL', PATHINFO_DETAILS | PATHINFO_TIMES | PATHINFO_RECURSIVE | PATHINFO_EXFS);
		
	}
	
	/**
	 * Renvoi le chemin absolue vers la racine du système de fichier interne à Soho.
	 * 
	 * @return string
	 */
	public function getRootDirectory() {
		return $this->rootDir;
	}
	
	/**
	 * Return a file unique ID, according to the given path. The path is
	 * relative to rootPath.
	 *
	 * @param string $node
	 * @return string
	 */
	public static function getNodeUID($node) {
	
		$fileId = md5($node);
	
		return substr($fileId, 0, 8) . '-' .
				substr($fileId, 8, 8) . '-' .
				substr($fileId, 16, 8) . '-' .
				substr($fileId, 24, 8);
	
	}
	
	/**
	 * @see Soho_Plugin_Files::checkPrivileges()
	 */
	public function checkCurrentUserPrivileges($node, $requiredPrivileges, $throwException = true) {
		return $this->checkPrivileges($node, $requiredPrivileges, $this->getCurrentUserPrivilegeSet($node), $throwException);
	}
	
	/**
	 * @param string $node Chemin vers le noeud de données.
	 * @return string[]
	 */
	public function getCurrentUserPrivilegeSet($node) {
		return WG::user() !== null ? $this->getUserPrivilegeSet(WG::user(), $node) : array();
	}
	
	/**
	 * Renvoi les priviléges d'un utilisateur.
	 * 
	 * @param Moodel<TeamMember> $user
	 * @param string $node Chemin vers le noeud de donnée.
	 * @return string[]
	 */
	public function getUserPrivilegeSet(Moodel $user, $node) {

		// On s'assure que le chemin vers le noeud ne finisse pas par un slash
		if (substr($node, -1) === '/') {
			$node = substr($node, 0, -1);
		}
		
		// On s'assure que le chemin vers le noeud commence par un slash
		if (substr($node, 0, 1) != '/') {
			$node = "/$node";
		}
		
		// Tableau de sortie, contenant les priviléges de l'utilisateur sur le noeud
		$priv = array();
		
		// Par défaut, tout le monde peut voir le noeud racine
		if ($node === '/') {
			$priv['read'] = 1;
		}
		
		#### GROUP RULES
		
		// On recupère le propriétaire et le groupe associé au noeud
		$owner = $this->exfs->getExFsData($node, 'own');
		$group = $this->exfs->getExFsData($node, 'grp');
		
		// Si l'utilisateur est dans le groupe, il peut voir le fichier, et
		// manipuler les noeuds à l'intérieur.
		if (substr($group, 0, 1) == '&' && $user->hasGroup(substr($group, 1))) {
			$priv['read'] = 1;
			$priv['write'] = 1;
			$priv['bind'] = 1;
			$priv['unbind'] = 1;
		}
		else if ($user->hasGroup($group)) {
			$priv['read'] = 1;
		}
		// Idem pour l'utilisateur
		if ('&' . $user->get('login') === $owner) {
			$priv['read'] = 1;
			$priv['write'] = 1;
			$priv['bind'] = 1;
			$priv['unbind'] = 1;
		}
		else if ($user->get('login') === $owner) {
			$priv['read'] = 1;
			$priv['bind'] = 1;
			$priv['unbind'] = 1;
		}
		
		
/*

		#### CUSTOM RULES
		
		// On recupère le répertoire home de l'utilisateur
		// (il y a un slash avant et après, on retire celui après)
		$home = substr($user->getUserFolder(), 0, -1);

		// Les répertoires publiques peuvent être lus
		if ($node == '/' || $node == '/home') {
			$priv['read'] = 1;
		}
		
		// Les fichiers qui se trouvent dans le home sont disponibles à l'utilisateur
		if ($node == $home) {
			$priv['read'] = 1;
			$priv['bind'] = 1;
			$priv['unbind'] = 1;
		}
		else if (substr($node, 0, strlen($home)) . '/' == $home . '/') {
			$priv['read'] = 1;
			$priv['bind'] = 1;
			$priv['unbind'] = 1;
			$priv['write'] = 1;
			$priv['write-property'] = 1;
		}
*/
		#### FLAG RULES
		
		// Les system-admin peuvent tous voir et modifier les propriétés
		if ($user->hasFlag('S')) {
			$priv['read'] = 1;
			$priv['write-property'] = 1;
		}
		
		// Root peut tout faire
		if ($user->hasFlag('Z')) {
			$priv['read'] = 1;
			$priv['bind'] = 1;
			$priv['unbind'] = 1;
			$priv['write'] = 1;
			$priv['write-property'] = 1;
		}
		
		// On renvoi la liste des priviléges
		return array_keys($priv);
	}
	
	/**
	 * Checks if the current user has the specified privilege(s).
	 *
	 * You can specify a single privilege, or a list of privileges.
	 * This method will throw an exception if the privilege is not available
	 * and return true otherwise.
	 *
	 * @param string $node Chemin vers le noeud de données.
	 * @param array|string $requiredPrivileges Les priviléges à posséder pour accéder à la ressource.
	 * @param array $userPrivileges Les priviéges de l'utilisateur sur la ressource.
	 * @param bool $throwExceptions if set to false, this method won't through exceptions.
	 * @throws WGFilesNeedPrivilegesException
	 * @return bool
	 */
	public static function checkPrivileges($node, $requiredPrivileges, $userPrivileges, $throwExceptions = true) {

		// On s'assure que les priviléges soient en tableau 
		if (!is_array($requiredPrivileges)) {
			$requiredPrivileges = array($requiredPrivileges);
		}
	
		/// Debug
		//echo "<checkPrivileges uri='$node' required-privileges=[".implode(',', $requiredPrivileges)."] />";
	
		// Ce tableau contiendra les priviléges qui ne sont pas respectés
		$failed = array();
		
		// On parcours les priviléges réquis
		foreach ($requiredPrivileges as $priv) {
			
			// Si l'utilisateur ne posséde pas de privilége, on l'ajoute dans le tableau de ratés 
			if (!in_array($priv, $userPrivileges)) {
				$failed[] = $priv;
			}
			
		}
	
		// Si des priviléges ont été 'ratés'
		if (sizeof($failed) > 0) {
			if ($throwExceptions) {
				throw new WGFilesNeedPrivilegesException($node, $failed);
			}
			return false;
		}
	
		// Si tout est accepté, on renvoi true
		return true;
	
	}
	
	/**
	 * 
	 * @param string $node Chemin vers le noeud de données.
	 * @param boolean $isFolder True pour tester si c'est un dossier, false pour un
	 * 	fichier, ou null pour uniquement tester que le nom existe.
	 * @param boolean $throwExceptions
	 * @return string Chemin "nettoyé" vars le noeud.
	 * @return null En cas d'erreur.
	 * @throws WGFilesIOException
	 */
	public function checkNode($node, $isFolder = null, $throwExceptions = true) {
		
		// On commence par nettoyer le chemin
		$node = self::cleanpath($node, $isFolder);
			
		// On vérifie qu'il s'agisse d'un dossier valide (si demandé)
		if ($isFolder === true) {
			if (is_dir($this->rootDir . $node)) {
				return $node;
			}
			// On lève une exception si le noeud n'existe pas
			else if ($throwExceptions) {
				throw new WGNotFolderException("Folder not found: $node");
			}
		}
		// On vérifie qu'il s'agisse d'un fichier valide (si demandé)
		else if ($isFolder === false) {
			if (is_file($this->rootDir . $node)) {
				return $node;
			}
			// On lève une exception si le noeud n'existe pas
			if ($throwExceptions) {
				throw new WGNotFilesException("File not found: $node");
			}
		}
		// Sinon on vérifie simplement que le nom existe
		else if (file_exists($this->rootDir . $node)) {
			return $node;
		}
		
		// On lève une exception si le noeud n'existe pas
		if ($throwExceptions) {
			throw new WGNodeNotFoundIOException("Node not found: $node");
		}
		
		// On renvoi null pour indique qu'il y a eu une erreur
		return null;
		
	}
	
	/**
	 * Nettoyer un chemin de type fichier.
	 * 
	 * @param string $path
	 * @param boolean $finalslash Indique si un slash final doit suivre le chemin. Le réglage
	 *  initiale est null, dans ce cas aucune vérification ne sera faite.
	 * @param string $homeDir Répertoire ciblé quand on utilise le token spécial '~'
	 * @return string
	 */
	public static function cleanpath($path, $finalslash = null, $homeDir = '') {
		
		// Chemin vide = le répertoire racine
		if ($path === '') return '/';
		
		// Si le chemin commence par un slash, on considère qu'il s'agit du root
		if (substr($path, 0, 1) === '/') {
			$path = '.' . $path;
		}
		
		// Tableau de sortie
		$out = array();
		
		// On parcours les blocs du chemin (séparés par des slashes)
		foreach (explode('/', str_replace('\\', '/', $path)) as $token) {
			
			// Pour être certain
			$token = trim($token);
			
			// Le token spécial . remet le chemin à zero
			if ($token === '.') {
				$out = array();
				continue;
			}
			
			// Le token spécial .. revient un répertoire en avant 
			if ($token === '..') {
				if (sizeof($out) > 0) {
					array_pop($out);
				}
				continue;
			}
			
			// Le token spécial ~ revient au répertoire home (c-à-d la variable $homeDir)
			// TODO Il faudrait que $homeDir ne soit pas un paramètre, mais un chemin déterminé
			// dans l'init. En fait, il faudrait p'tet rendre la méthode non-statique ?
			// Et surtout que $homeDir ne doit avoir de slash ni devant ni derrière...
			/*if ($token === '~') {
				$out = array($homeDir);
				continue;
			}*/
			
			// On évite les tokens vides ou n'étant pas valides
			if ($token === '' || preg_match('/^[\.]{1,}$/', $token)) {
				continue;
			}
			
			// On ajoute le token dans la liste
			$out[] = $token;

		}

		// On rassemble le chemin
		$path = '/' . implode('/', $out);

		// Traitement du slasg final
		if ($finalslash === true && substr($path, -1) !== '/') {
			return $path . '/';
		}
		else if ($finalslash === false && substr($path, -1) === '/') {
			return substr($path, 0, -1);
		}

		return $path;
	}
	
	/**
	 * Supprimer un fichier ou un dossier. 
	 * 
	 * @param string $node Chemin vers le noeud.
	 * @param boolean $recursive
	 * @throws WGFilesIOException En cas d'erreur I/O
	 * @throws WGFilesSecurityException Si l'utilisateur courant n'a pas le droit de supprimer le noeud.
	 * @return void
	 */
	public function unlink($node, $recursive = true) {
		
		// On vérifie que $node soit un chemin valide
		// En cas d'erreur, une exception WGFilesIOException sera levée
		// Au passage, $node est nettoyé
		$node = $this->checkNode($node, null, true);
		
		// On recupère le chemin vers le noeud parent
		$parent = dirname($node);
		if ($parent !== '/') {
			$parent .= '/';
		}
		
		// On vérifie que l'utilisateur puisse détruire la ressource
		// En cas d'erreur, une exception WGFilesNeedPrivilegesException sera levée
		$this->checkCurrentUserPrivileges($parent, 'unbind', true);
		
		// Chemin complet vers la ressource
		$path = $this->rootDir . $node;
		
		// Traitement pour les dossiers
		if (is_dir($path)) {

			// Traitement récursif
			if ($recursive) {
				
				// On liste les sous-répertoires
				$list = scandir($path);
				
				// Traitement des erreurs
				if (!is_array($list)) {
					throw new WGFilesIOException("Unable to fetch directory: $node");
				}
				
				// On parcours les sous-éléments
				foreach ($list as $item) {
					if ($item == '.' || $item == '..') continue;
					$this->unlink("$node/$item", $recursive);
				}
				
			}
			
			// On tente la suppression du répertoire
			// Si le répertoire n'est pas vide, et si le traitement récursif n'a pas été fait
			// une erreur sera levée
			if (!is_writable($path) || @!rmdir($path)) {
				throw new WGFilesIOException("Unable to delete directory: $node");
			}
			
			// On supprime aussi les données EXFS
			$this->exfs->deleteExFsData($node);
			
		}
		// Traitement pour les fichiers
		else {
			if (!is_writable($path) || @!unlink($path)) {
				throw new WGFilesIOException("Unable to delete file: $node");
			}
			// On supprime aussi les données EXFS
			$this->exfs->deleteExFsData($node);
		}
		
	}
	
	/**
	 * Créer des répertoires.
	 * 
	 * @param string $node Chemin complet vers les répertoires à créer.
	 * @throws WGFilesIOException En cas d'erreur I/O
	 * @throws WGFilesSecurityException Si l'utilisateur courant n'a pas le droit bind dans le dossier.
	 * @return void
	 */
	public function mkdir($node, $recursive = true) {
		
		// On commence par nettoyer le chemin
		$node = self::cleanpath($node, true);
		
		// On parcours les blocs du chemin pour trouver le dernier répertoire qui existe.
		$path = '/';
		foreach (explode('/', $node) as $token) {
			if (empty($token)) continue;
			if (!is_dir($this->rootDir . $path . '/' . $token)) {
				break;
			}
			$path .= $token . '/';
		}
		
		// On vérifie que l'utilisateur ai le droit de créer des répertoires dans ce dossier
		$this->checkCurrentUserPrivileges($path, 'bind', true);

		// Chemin complet vers la ressource
		// On ne vérifie pas particulièrement la validité du chemin, la fonction mkdir le fait pour nous
		$path = $this->rootDir . $node;
		
		// Le répertoire existe déjà
		if (is_dir($path)) {
			throw new WGFilesIOException("Directory allready exists: $node");
		}
		
		// On tente de créer les répertoires
		if (@!mkdir($path, 0777, $recursive)) {
			throw new WGFilesIOException("Unable to make directory: $node");
		}
		
	}
	
	/**
	 * Change le nom d'un noeud.
	 * Contrairement à l'habitude d'unix, cette fonction ne permet pas de déplacer un noeud
	 * par renommage. Il faut utiliser move(). Cette fonction fait uniquement changer le nom.
	 *
	 * @param string $node Chemin complet vers le noeud à renommer.
	 * @param string $newName Nouveau nom de fichier.
	 * @param string $forbbiden Regex pour valider les caractères inderdits. Par défaut, tous les
	 *  chars de Windows sont pris en compte (c-à-d \ / : * ? " < > |).
	 * @throws WGFilesIOException En cas d'erreur I/O
	 * @throws WGFilesSecurityException Si l'utilisateur courant n'a pas le droit de renommer ce noeud.
	 * @throws WGInvalidArgumentException Si le nom $newName est invalide par rapport à $forbidden
	 * @return void
	 */
	public function rename($node, $newName, $forbidden = '/[\/\:\*\?\<\>\|\\\\]/i') {
	
		// On vérifie que $node soit un chemin valide
		// En cas d'erreur, une exception WGFilesIOException sera levée
		// Au passage, $node est nettoyé
		$node = $this->checkNode($node, null, true);
		
		// On vérifie que l'utilisateur puisse modifier la ressource
		// En cas d'erreur, une exception WGFilesNeedPrivilegesException sera levée
		$this->checkCurrentUserPrivileges($node, 'write', true);
		
		// Chemin complet vers la ressource
		$path = $this->rootDir . $node;
		
		// On valide le nouveau nom
		if (preg_match($forbidden, $newName)) {
			throw new WGInvalidArgumentException("Invalid name");
		}
		
		// On tente de renommer le noeud
		if (@!rename($path, dirname($path) . "/$newName")) {
			throw new WGFilesIOException("Unable to rename: $node");
		}
		
	}
	
	public function move($node, $targetDirectory) {
		
		// On vérifie que $node soit un chemin valide
		// En cas d'erreur, une exception WGFilesIOException sera levée
		// Au passage, $node est nettoyé
		$node = $this->checkNode($node, null, true);
		
		// On vérifie que l'utilisateur puisse modifier la ressource
		// En cas d'erreur, une exception WGFilesNeedPrivilegesException sera levée
		$this->checkCurrentUserPrivileges($node, 'write', true);
		
		// On vérifie que $targetDirectory soit un répertoire valide
		// En cas d'erreur, une exception WGFilesIOException sera levée
		// Au passage, $targetDirectory est nettoyé
		$targetDirectory = $this->checkNode($targetDirectory, true, true);
		
		// On vérifie que l'utilisateur puisse ajouter des noeuds dans le dossier cible
		// En cas d'erreur, une exception WGFilesNeedPrivilegesException sera levée
		$this->checkCurrentUserPrivileges($targetDirectory, 'bind', true);
		
		// Chemin complet vers le noeud actuellement
		$pathNode = $this->rootDir . $node;
		
		// Chemin complet vers le noeud après le move
		$pathTarget = $this->rootDir . $targetDirectory;
		
		// On tente de renommer le chemin du noeud
		if (@!rename($pathNode, $pathTarget)) {
			throw new WGFilesIOException("Unable to move: $node to $targetDirectory");
		}
		
	}
	
	/**
	 * Renvoi le chemin aboslue dans le système de fichier OS du noeud donné.
	 * Cette méthode ne vérifie pas l'existance du chemin de noeud passé.
	 * 
	 * @param string $node Chemin vers le neoud de données.
	 */
	public function realpath($node) {
		return $this->rootDir . self::cleanpath($node);
	}
	
	/**
	 * Renvoi des informations sur un noeud.
	 * 
	 * Cette fonction n'implémente pas de solution de cache par elle même, mais elle se base
	 * sur celui de PHP pour les fonctions de fichier. Voyez la fonction clearstatcache() pour
	 * plus de détails. Si l'option PATHINFO_RECURSIVE est spécifiée, cette méthode utilisera
	 * Soho_Plugin_Files::foldersize() pour calculer la taille des répertoires, et utilisera
	 * par défaut un cache interne.
	 * 
	 * ATTENTION ! Cette méthode ne test PAS les ACL ! Par contre, si l'option PATHINFO_EXFS
	 * ou PATHINFO_ALL sont utilisées, l'utilisateur devra disposer du droit 'read' sur le noeud.
	 * 
	 * @param string $node
	 * @param int $opts
	 * @throws WGFilesIOException En cas d'erreur I/O. Mais n'est pas levée si le fichier n'existe pas.
	 * @throws WGFilesSecurityException Si l'utilisateur courant n'a pas le droit de lire ce fichier
	 * @return mixed[] En cas de succès, les données demandées
	 * @return|null Si le noeud n'existe pas
	 */
	public function pathinfo($node, $opts = 0, $maxRecursion = 20) {
		
		// On commence par nettoyer le répertoire
		$node = self::cleanpath($node);
		
		// On assemble le chemin complet vers l'élément
		$path = $this->rootDir . $node;
		
		// Si le fichier n'existe pas, on renvoi null
		if (!file_exists($path)) {
			return null;
		}
		
		// On prépare le tableau de sortie avec les informations
		$info = array();
		
		// On renseigne le chemin relatif
		$info['path'] = $node;
		
		// On renseigne le chemin absolue
		$info['realpath'] = $path;
		
		// Par défaut
		$info['type'] = '-';
		
		// Status d'execution
		$info['exec'] = is_executable($path);
		
		// Temps
		if ($opts & PATHINFO_TIMES) {
			$info['ctime'] = filectime($path);
			$info['mtime'] = filemtime($path);
			$info['atime'] = fileatime($path);
		}
		
		// Spécifique aux liens symboliques
		if (is_link($path)) {
			$info['type'] = 'l';
		}
		
		// Spécifique aux fichiers
		else if (is_file($path)) {
			$info['size'] = filesize($path);
		}
		
		// Spécifique aux les dossiers
		else if (is_dir($path)) {
				
			$info['type'] = 'd';
				
			if ($opts & PATHINFO_RECURSIVE) {
				$info['size'] = self::foldersize($path, $maxRecursion);
			}
				
		}
		
		// Ajouter les détails sur le chemin
		if ($opts & PATHINFO_DETAILS) {
			$info = array_merge($info, pathinfo($path));
		}
		
		// Intégrer les données EXFS du noeud
		if ($opts & PATHINFO_EXFS) {
			$info['exfs'] = $this->exfs->getExFsData($node);
		}
		
		// On renvoi les informations
		return $info;
		
	}
	
	/**
	 * Lister les éléments d'un répertoire.
	 *
	 * @params string $node Chemin vers le noeud de données.
	 * @throws WGFilesIOException En cas d'erreur I/O
	 * @throws WGFilesSecurityException Si l'utilisateur courant n'a pas le droit de lister ce dossier.
	 */
	public function ls($node, $suffixDir = false) {
	
		// On vérifie que $node soit un répertoire valide
		// En cas d'erreur, une exception WGFilesIOException sera levée
		// Au passage, $node est nettoyé
		$node = $this->checkNode($node, true, true);
	
		// On vérifie que l'utilisateur puisse lire la ressource
		// En cas d'erreur, une exception WGFilesNeedPrivilegesException sera levée
		$this->checkCurrentUserPrivileges($node, 'read', true);
	
		// On liste les éléments du dossier
		$list = scandir($this->rootDir . $node);
	
		// On retire . et ..
		array_shift($list);
		array_shift($list);
		
		// On parcours les éléments
		foreach ($list as $k => &$file) {
			// On vérifie qu'ils soit visibles
			if (!$this->checkCurrentUserPrivileges($node . $file, 'read', false)) {
				unset($list[$k]);
			}
			// On indique qu'il s'agit d'un répertoire si c'est demandé par $suffixDir
			if ($suffixDir && is_dir($this->rootDir . $node . '/' . $file)) {
				$file .= '/';
			}
		}
	
		// On renvoi la liste
		return $list;
	
	}

	/**
	 * Recherche des chemins qui vérifient un masque.
	 * 
	 * Recherche tous les chemins qui vérifient le masque pattern, en suivant les règles
	 * utilisées par la fonction glob() de la libc, qui sont les mêmes que celles utilisées
	 * par le Shell en général.
	 * 
	 * @param string $pattern Le masque. Aucun remplacement de tilde (~) ou de paramètre n'est fait.
	 * @param int $flags Les drapeaux valides sont :
	 * 	- GLOB_MARK : Ajoute un slash final à chaque dossier retourné
	 * 	- GLOB_NOSORT : Retourne les fichiers dans l'ordre d'apparence (pas de tri)
	 * 	- GLOB_NOCHECK : Retourne le masque de recherche si aucun fichier n'a été trouvé
	 * 	- GLOB_NOESCAPE : Ne protège aucun métacaractère d'un antislash
	 * 	- GLOB_BRACE : Remplace {a,b,c} par 'a', 'b' ou 'c'
	 * 	- GLOB_ONLYDIR : Ne retourne que les dossiers qui vérifient le masque
	 * 	- GLOB_ERR : Stop lors d'une erreur (comme des dossiers non lisibles), par défaut, les erreurs sont ignorées.
	 * @return Retourne un tableau contenant les fichiers et dossiers correspondant au masque,
	 *  un tableau vide s'il n'y a aucune correspondance, ou FALSE si une erreur survient.
	 */
	public function glob($pattern, $flags = 0) {

		// Chemin absolue
		$path = $this->rootDir . self::cleanpath($pattern);

		// Liste des éléments
		$list = glob($path, $flags);
		
		// Erreur
		if ($list === false) {
			return false;
		}
		
		// On parcours la liste
		foreach ($list as $k => &$v) {
			
			// Cette variable contiendra le chemin relatif vers le noeud
			$node = "";
			
			// On vérifie que le fichier soit bien dans la portée du gestionnaire de fichier
			if (!$this->isInsideRootDir($v, $node)) {
				unset($list[$k]);
				continue;
			}
			
			// Si l'utilisateur n'a pas le droit de voir le fichier, il est retiré du listing
			if (!$this->checkCurrentUserPrivileges($node, 'read', false)) {
				unset($list[$k]);
				continue;
			}
			
			// Si le noeud est accepté, on renvoi le chemin relatif
			$v = $node;
			
		}
		
		// On renvoi la liste des éléments
		return $list;		
	}
	
	/**
	 * Renvoi TRUE si le chemin donné est bien dans la portée du gestionnaire.
	 * 
	 * Attention: la variable $realpath n'est PAS traitée par cette fonction,
	 * il doit absolument être "propre", c-à-d il doit provenir de realpath().
	 * 
	 * @param string $realpath
	 * @param string $relativepath Si spécifié, cette variable sera modifiée et contiendra
	 *   le chemin realatif du noeud s'il est accepté.
	 * @return boolean
	 */
	public function isInsideRootDir($realpath, &$relativepath = null) {
		
		// La base du gestionnaire
		$base = $this->rootDir . '/';
		
		// La taille du chemin vers la base 
		$length = strlen($base);
		
		// On vérifie que chemin commence bien par la base
		if (substr($realpath, 0, $length) !== $base) {
			return false;
		}
	
		// On modifie le chemin relatife s'il est demandé 
		$relativepath = substr($realpath, $length - 1);
		
		// On renvoi TRUE
		return true;
	}
	
	/**
	 * 
	 *
	 * @param string $node Chemin vers le noeud de données.
	 * @param string $property Le nom de la propriété àç  renvoyer.
	 * @throws WGFilesIOException En cas d'erreur I/O
	 * @throws WGFilesSecurityException Si l'utilisateur courant n'a pas le droit de lire ce fichier
	 * @return string|null
	 */
	public function getNodeProperty($node, $property) {
		
		// On vérifie que $node soit un noeud valide
		// En cas d'erreur, une exception WGFilesIOException sera levée
		// Au passage, $node est nettoyé
		$node = $this->checkNode($node, null, true);
		
		// On vérifie que l'utilisateur puisse lire la ressource
		// En cas d'erreur, une exception WGFilesNeedPrivilegesException sera levée
		$this->checkCurrentUserPrivileges($node, 'read', true);
		
		// On renvoi les données EXFS du noeud.
		return $this->exfs->getExFsData($node, $node);
		
	}
	
	/**
	 * 
	 *
	 * @param string $node Chemin vers le noeud de données.
	 * @param string $property
	 * @param mixed $value
	 * @throws WGFilesIOException En cas d'erreur I/O
	 * @throws WGFilesSecurityException Si l'utilisateur courant n'a pas le droit d'écrire des properties sur ce fichier
	 * @return void
	 */
	public function setNodeProperty($node, $property, $value) {
		
		// On vérifie que $node soit un noeud valide
		// En cas d'erreur, une exception WGFilesIOException sera levée
		// Au passage, $node est nettoyé
		$node = $this->checkNode($node, null, true);
		
		// On vérifie que l'utilisateur puisse ecrire des properties sur la ressource
		// En cas d'erreur, une exception WGFilesNeedPrivilegesException sera levée
		$this->checkCurrentUserPrivileges($node, 'write-property', true);
		
		// Si tout se passe bien, on fait la modification
		$this->exfs->setExFsData($node, $property, $value);
		
	}
	
	/**
	 * Renvoi le groupe auquel ce noeud appartient.
	 *
	 * @param string $node Chemin vers le noeud de données.
	 * @throws WGFilesIOException En cas d'erreur I/O
	 * @throws WGFilesSecurityException Si l'utilisateur courant n'a pas le droit de lire ce fichier
	 * @return string|null
	 */
	public function getGroup($node) {
		return $this->getNodeProperty($node, 'grp');	
	}
	
	/**
	 * Renvoi le propriétaire auquel ce noeud appartient.
	 *
	 * @param string $node Chemin vers le noeud de données.
	 * @throws WGFilesIOException En cas d'erreur I/O
	 * @throws WGFilesSecurityException Si l'utilisateur courant n'a pas le droit de lire ce fichier
	 * @return string|null
	 */
	public function getOwner($node) {
		return $this->getNodeProperty($node, 'own');
	}
	
	/**
	 * Changer le propriétaire d'un noeud.
	 *
	 * @param string $node Chemin vers le noeud de données.
	 * @param string $owner Nom du propriétaire.
	 * @throws WGFilesIOException En cas d'erreur I/O
	 * @throws WGFilesSecurityException Si l'utilisateur courant n'a pas le droit d'écrire des properties sur ce fichier
	 * @return void
	 */
	public function setOwner($node, $owner) {
		if (!preg_match('/^[a-z]{3,}$/i', "$owner")) {
			throw new WGInvalidArgumentException("Invalid user name: $owner");
		}
		$this->setNodeProperty($node, 'own', "$owner");
	}
	
	/**
	 * Changer le groupe d'un noeud.
	 *
	 * @param string $node Chemin vers le noeud de données.
	 * @param string $group Nom du groupe.
	 * @throws WGFilesIOException En cas d'erreur I/O
	 * @throws WGFilesSecurityException Si l'utilisateur courant n'a pas le droit de lire ou d'écrire ce fichier
	 * @throws WGInvalidArgumentException Si le nom du groupe est invalide
	 * @return void
	 *
	 */
	public function setGroup($node, $group) {
		if ($group !== '-') {
			if (!preg_match('/^[a-z]{2,}$/i', "$group")) {
				throw new WGInvalidArgumentException("Invalid group name: $owner");
			}
		}
		$this->setNodeProperty($node, 'grp', "$group");
	}
	
	public function isDirectory($node) {
		if (!is_string($node) || empty($node)) return false;
		return is_dir($this->realpath(self::cleanpath($node)));
	}
	
	public function isFile($node) {
		if (!is_string($node) || empty($node)) return false;
		return is_file($this->realpath(self::cleanpath($node)));
	}
	
	public function isLink($node) {
		if (!is_string($node) || empty($node)) return false;
		return is_link($this->realpath(self::cleanpath($node)));
	}
	
	public function nodeExists($node) {
		if (!is_string($node) || empty($node)) return false;
		return file_exists($this->realpath(self::cleanpath($node)));
	}
	
	/**
	 * Calcule la taille d'un répertoire, en prenant en compte les sous répertoires.
	 * 
	 * @param string $node Chemin vers le répertoire.
	 * @param int $maxDepth Nombre max de recursion (défaut 0 pour aucune limite)
	 * @param boolean $useCache Stoquer les résultats en cache, améliore grandement la récursion.
	 * @param int $current Indique le niveau actuel de récursion (ne pas toucher à cet argument)
	 * @return int|float Float si une imprecision apparait
	 */
	public static function foldersize($node, $maxDepth = 0, $useCache = true, $currentDepth = 0) {
		
		// Lecture du cache
		if ($useCache && array_key_exists($node, self::$cache_foldersize)) {
			return self::$cache_foldersize[$node];
		}
		
		// Si un niveau max de récursion est indiqué, on retourne -1 pour indiquer que 
		// la taille du noeud n'a pas été calculé
		// TODO Ce ne serait pas mieux d'éviter ça AVANT de lancer la méthode récursivement ?
		// TODO C-à-d dans le foreach du scandir
		if ($maxDepth > 0 && $currentDepth >= $maxDepth) {
			return -1;
		}
		
		// S'il n'est pas possible de lire le dossier; on renvoi -2 pour indiquer l'erreur
		if (!is_dir($node) || !is_readable($node)) {
			return -2;
		}
		
		// On liste les éléments du répertoire
		$list = scandir($node);
		
		// Au cas où scandir aurait un problème
		if (!$list) {
			return -3;
		}
		
		// On sort . et .. de la liste
		array_shift($list);
		array_shift($list);
		
		// Compteur en octets de la taille (variable de sortie)
		$s = 0;
		
		// On parcours les éléments du répertoire
		foreach ($list as $item) {
			
			// Récursion pour les dossiers
			if (is_dir("$node/$item")) {
				
				// On recupère la taille du sous répertoire
				$c = self::foldersize("$node/$item", $maxDepth, $currentDepth + 1);
				
				// Si la fonction renvoi une erreur, on ajoute une imprécision dans le résultat
				// (c-à-d que $s devient un nombre flottant)
				if ($c < 0) {
					$s += 0.00001;
				}
				// Sinon on ajoute la taille du sous-répertoire
				else {
					$s += $c;
				}

			}
			
			// Pour les autres types de noeuds on récupère la taille
			else {
				// A noter que PHP 
				$s += filesize("$node/$item");
			}
			
		}
		
		// Enregistrement en cache
		if ($useCache) {
			self::$cache_foldersize[$node] = $s;
		}

		// On renvoi la taille en octets
		return $s;
		
	}

}

// Exception de base pour le plugin Files
class WGFilesPluginException extends WGPluginException { }

// Exceptions particulières
class WGFilesIOException extends WGFilesPluginException { }
class WGFilesSecurityException extends WGFilesPluginException { }
class WGFilesNeedPrivilegesException extends WGFilesSecurityException {
	
	public function __construct($node, $privileges, $previous = null) {
		$privileges = implode(', ', $privileges);
		parent::__construct("Required privileges $privileges on $node", 403, $previous);
	}
	
}

class WGNotFolderException extends WGFilesIOException { }
class WGNotFilesException extends WGFilesIOException { }
class WGNodeNotFoundIOException extends WGFilesIOException { }

// On installe le plugin dans WG
WG::addPlugin(new Soho_Plugin_Files());

?>