<?php

class Soho_CLI_Files extends Soho_CLI_Base {	
	
	/**
	 * Auto-completion pour la commande 'll'
	 */
	function handle_ll_autocomplete($args, &$r) {
		$this->handle_ls_autocomplete($args, $r);
	}
	
	/**
	 * @cmdAlias ls
	 */
	function handle_ll($file, $cmd, $params, $argv) {
		return $this->handle_ls($file, $cmd, $params, $argv);
	}
	
	/**
	 * Auto-completion pour la commande 'ls'
	 */
	function handle_ls_autocomplete($args, &$r) {
		// 1er argument = chemin vers un dossier
		if (sizeof($args) === 2) {
			$this->autocomplete_files($args, $r);
		}
	}
	
	/**
	 * List directory contents.
	 *
	 * @requireFlags u
	 * @allowedParams
	 * @cmdPackage Files
	 */
	function handle_ls($file, $cmd, $params, $argv) {
		
		// On vérifie droits et arguments
		if (!$this->check()) {
			return false;
		}
		
		// Le répertoire cible
		$node = isset($_SESSION['cd']) ? $_SESSION['cd'] : '/';
		
		// Si un chemin est passé en paramètres, on modifie le répertoire cible
		if (isset($params[0])) {
			$node = implode(' ', $params);
			// Par rapport à l'emplacement actuel
			if (substr($node, 0, 1) !== '/' && isset($_SESSION['cd'])) {
				$node = $_SESSION['cd'] . $node;
			}
		}

		try {
			
			// On recupère l'API de gestion de fichier
			$api = WG::files();
			
			// On nettoye le chemin
			$node = $api->cleanpath($node, true);
			
			// On demande la liste du répertoire
			$list = $api->ls($node);
			
		}
		// En cas d'erreur, on traite les exceptions
		catch (Exception $ex) {
			echo "$cmd: " . $ex->getMessage() . PHP_EOL;
			return false;
		}
		
			// On tente aussi de récupérer l'API de versioncontrol
		$vc = null;
		try {
			$vc = WG::versioncontrol();
		}
		catch (Exception $ex) { }
		
		// Compteur de fichiers
		$c = 0;
		
		// Tableau de sortie
		$entries = array();
		
		// Tableau de calcul des longueurs max
		$length = array(
			'owner' => 0,
			'group' => 0
		);
		
		// Parcours de la liste des items
		foreach ($list as $item) {
			
			// On recupère les info sur ce noeud
			$info = $api->pathinfo($node . $item, PATHINFO_TIMES | PATHINFO_EXFS);
			
			// On incrémente le compteur de fichiers
			$c++;
			
			// Privileges
			$priv = $api->getCurrentUserPrivilegeSet($node . $item);
			
			// On enregistre l'entrées
			$entries[] = array(	$item, $priv, $info);
			
			// On détermine les tailles maximales des champs 'owner' et 'group'
			if (isset($info['exfs']['own'])) {
				$length['owner'] = max($length['owner'], strlen($info['exfs']['own']));
			}
			if (isset($info['exfs']['grp'])) {
				$length['group'] = max($length['group'], strlen($info['exfs']['grp']));
			}
			
		}
		
		// On fait un peu de nettoyage
		unset($list, $item, $priv, $info);
		
		// Affichage du nombre total de fichiers affichés
		echo "Total: $c" . PHP_EOL;
		
		// On détermine si l'utilisateur est root
		$isRoot = WG::user()->hasFlag('Z');
		
		// On parcours les items
		foreach ($entries as $entry) {
			
			// On récupère les infos du noeud
			list($item, $priv, $info) = $entry;
			
			// Erreur
			if (!is_array($info)) {
				echo '                            I/O Error           ' . $this->red($this->bold($item)) . PHP_EOL;
				continue;
			}
			
			// Type
			echo $info['type'];
			
			// Accès pour l'utilisateur
			echo in_array('read', $priv) ? 'r' : '-';
			echo in_array('write', $priv) ? 'w' : '-';
			
			// Pour l'instance on ne gêre pas le user/group/everybody
			
			// Executable
			echo $info['exec'] ? 'x ' : '- ';
			
			// Numéro de révision
			$rev = 0;
			if ($vc) {
				$rev = sizeof($vc->getFileRevisions($api->getNodeUID($node . $item)));
			}
			echo ($rev === 0 ? '0' : $rev) . ' ';
			
			// Owner
			$owner = $isRoot ? $info['exfs']['own'] : str_replace('&', '', $info['exfs']['own']);
			echo str_pad('' . $owner, $length['owner'] + 1);
			
			// Group
			$group = $isRoot ? $info['exfs']['grp'] : str_replace('&', '', $info['exfs']['grp']);
			echo str_pad(''. $group, $length['group'] + 1);
			
			// Taille
			if (array_key_exists('size', $info)) {
				echo str_pad((is_float($info['size'])?'~':'') . "" . $info['size'], 15, ' ', 0);
			}
			else {
				echo '               ';
			}
			
			// Date de modification
			echo ' ' . date('Y/m/d H:i:s', $info['mtime']) . ' ';
			
			// Nom de fichier
			if ($info['type'] == 'd') {
				echo $this->blue($this->bold($item)) . '/';
			}
			else {
				echo $item;
			}
			
			echo PHP_EOL;
			
		}

		return true;
	}
	
	/**
	 * Print name of current/working directory.
	 *
	 * @requireFlags u
	 * @allowedParams
	 * @cmdPackage Files
	 */
	function handle_pwd($file, $cmd, $params, $argv) {
		
		// On vérifie droits et arguments
		if (!$this->check()) {
			return false;
		}
		
		// On affiche le répertoire actuel, stoqué en sessions
		echo (isset($_SESSION['cd']) ? $_SESSION['cd'] : '/') . PHP_EOL;
		
		return true;
		
	}
	
	/**
	 * Auto-completion pour la commande 'cd'
	 */
	function handle_cd_autocomplete($args, &$r) {
		// 1er argument = chemin vers un dossier cible
		if (sizeof($args) === 2) {
			$this->autocomplete_files($args, $r);
		}
	}
	
	/**
	 * Change the working directory.
	 *
	 * @requireFlags u
	 * @allowedParams
	 * @cmdPackage Files
	 */
	function handle_cd($file, $cmd, $params, $argv) {
		
		// On vérifie droits et arguments
		if (!$this->check()) {
			return false;
		}
		
		// Aucun argument 
		if (!isset($params[0])) {
			return false;
		}

		// Recupération du chemin vers le noeud de données
		$node = implode(' ', $params);
		
		// Par rapport à l'emplacement actuel
		if (substr($node, 0, 1) !== '/' && isset($_SESSION['cd'])) {
			$node = $_SESSION['cd'] . $node;
		}
		
		try {
				
			// On recupère l'API de gestion de fichier
			$api = WG::files();
			
			// On vérifie que $node soit un répertoire valide
			// En cas d'erreur, une exception WGFilesIOException sera levée
			// Au passage, $node est nettoyé
			$node = $api->checkNode($node, true, true);
	
			// On vérifie que l'utilisateur puisse lire la ressource
			// En cas d'erreur, une exception WGFilesNeedPrivilegesException sera levée
			$api->checkCurrentUserPrivileges($node, 'read', true);
				
			// Ok, on peut aller dans ce répertoire
			$_SESSION['cd'] = $node;
				
		}
		// En cas d'erreur, on traite les exceptions
		catch (Exception $ex) {
			echo "$cmd: " . $ex->getMessage() . PHP_EOL;
			return false;
		}
		
		return true;
	}
	
	/**
	 * Auto-completion pour la commande 'unlink'
	 */
	function handle_unlink_autocomplete($args, &$r) {
		// 1er argument = chemin vers la cible
		if (sizeof($args) === 2) {
			$this->autocomplete_files($args, $r);
		}
	}
	
	/**
	 * Delete a node and the file it refers to.
	 *
	 * @requireFlags u
	 * @cmdPackage Files
	 */
	function handle_unlink($file, $cmd, $params, $argv) {
	
		// On vérifie droits et arguments
		if (!$this->check()) {
			return false;
		}
		
		// Recursion
		$recursive = false;
		if (@$argv[0] == '-r') {
			$recursive = true;
			unset($argv[0]);
		}
		
		// Aucun argument
		if (sizeof($argv) < 1) {
			echo "Usage: $cmd [-r] NAME" . PHP_EOL;
			return false;
		}

		// Le noeud cible
		$node = implode(' ', $argv);
		
		// Par rapport à l'emplacement actuel
		if (substr($node, 0, 1) !== '/' && isset($_SESSION['cd'])) {
			$node = $_SESSION['cd'] . $node;
		}
		
		try {
		
			// On recupère l'API de gestion de fichier
			$api = WG::files();
			
			// On tente de supprimer le fichier
			$api->unlink($node, isset($params['r']));
			
		
		}
		// En cas d'erreur, on traite les exceptions
		catch (Exception $ex) {
			echo "$cmd: " . $ex->getMessage() . PHP_EOL;
			return false;
		}
		
	}
	
	/**
	 * Auto-completion pour la commande 'mkdir'
	 */
	function handle_mkdir_autocomplete($args, &$r) {
		// 1er argument = chemin vers la cible
		if (sizeof($args) === 2) {
			$this->autocomplete_files($args, $r);
		}
	}
	
	/**
	 * Create the directory(ies), if they do not already exist.
	 *
	 * @requireFlags u
	 * @allowedParams
	 * @cmdPackage Files
	 */
	function handle_mkdir($file, $cmd, $params, $argv) {
	
		// On vérifie droits et arguments
		if (!$this->check()) {
			return false;
		}
	
		// Aucun argument
		if (sizeof($argv) < 1) {
			echo "Usage: $cmd DIR" . PHP_EOL;
			return false;
		}
	
		// Le noeud cible
		$node = implode(' ', $argv);
	
		// Par rapport à l'emplacement actuel
		if (substr($node, 0, 1) !== '/' && isset($_SESSION['cd'])) {
			$node = $_SESSION['cd'] . $node;
		}
	
		try {
	
			// On recupère l'API de gestion de fichier
			$api = WG::files();
				
			// On tente la création du répertoire
			$api->mkdir($node, true);
	
		}
		// En cas d'erreur, on traite les exceptions
		catch (Exception $ex) {
			echo "$cmd: " . $ex->getMessage() . PHP_EOL;
			return false;
		}
	
	}
	
	/**
	 * Auto-completion pour la commande 'rename'
	 */
	function handle_rename_autocomplete($args, &$r) {
		// 1er argument = chemin vers la cible
		if (sizeof($args) === 2) {
			$this->autocomplete_files($args, $r);
		}
	}
	
	/**
	 * Rename the specified node.
	 *
	 * @requireFlags u
	 * @allowedParams
	 * @cmdPackage Files
	 */
	function handle_rename($file, $cmd, $params, $argv) {
	
		// On vérifie droits et arguments
		if (!$this->check()) {
			return false;
		}
		
		// Pas assez d'arguments
		if (!isset($params[1])) {
			echo "Usage: $cmd TARGET NEWNAME" . PHP_EOL;
			return false;
		}

		// On récupère les deux noms
		list($node, $name) = $params;
		
		// Par rapport à l'emplacement actuel
		if (substr($node, 0, 1) !== '/' && isset($_SESSION['cd'])) {
			$node = $_SESSION['cd'] . $node;
		}
		
		try {
	
			// On recupère l'API de gestion de fichier
			$api = WG::files();
				
			// On tente de renommer le noeud
			$api->rename($node, $name);
			
		}
		// En cas d'erreur, on traite les exceptions
		catch (Exception $ex) {
			echo "$cmd: " . $ex->getMessage() . PHP_EOL;
			return false;
		}
		
	}
	
	/**
	 * Auto-completion pour la commande 'move'
	 */
	function handle_move_autocomplete($args, &$r) {
		// 1er argument = chemin vers la cible
		if (sizeof($args) === 2) {
			$this->autocomplete_files($args, $r);
		}
	}
	
	/**
	 * Move a node to another directory.
	 *
	 * @requireFlags u
	 * @allowedParams
	 * @cmdPackage Files
	 */
	function handle_move($file, $cmd, $params, $argv) {
		
		// On vérifie droits et arguments
		if (!$this->check()) {
			return false;
		}
		
		// Pas assez d'arguments
		if (!isset($params[1])) {
			echo "Usage: $cmd TARGET DIR" . PHP_EOL;
			return false;
		}
		
		// On récupère les deux noms
		list($node, $dir) = $params;
	
		// Par rapport à l'emplacement actuel
		if (substr($node, 0, 1) !== '/' && isset($_SESSION['cd'])) {
			$node = $_SESSION['cd'] . $node;
		}
		if (substr($dir, 0, 1) !== '/' && isset($_SESSION['cd'])) {
			$dir = $_SESSION['cd'] . $dir;
		}

		try {
	
			// On recupère l'API de gestion de fichier
			$api = WG::files();
	
			// On tente de déplacer le noeud
			$api->move($node, $dir);
				
		}
		// En cas d'erreur, on traite les exceptions
		catch (Exception $ex) {
			echo "$cmd: " . $ex->getMessage() . PHP_EOL;
			return false;
		}
		
	}
		
	/**
	 * Auto-completion pour la commande 'chgroup'
	 */
	function handle_chgroup_autocomplete($args, &$r) {
		// 1er argument: nom de groupe
		if (sizeof($args) === 2) {
			$this->autocomplete_groups($args[1], $r);
		}
		// A partir du 2eme argument = chemins vers les cibles
		else if (sizeof($args) > 2) {
			$this->autocomplete_files($args, $r);
		}
	}
	
	/**
	 * Change node's group name.
	 *
	 * @requireFlags a
	 * @allowedParams
	 * @cmdPackage Files
	 */
	function handle_chgroup($file, $cmd, $params, $argv) {
	
		// On vérifie droits et arguments
		if (!$this->check()) {
			return false;
		}
	
		// Pas assez d'arguments
		if (!isset($params[1])) {
			echo "Usage: $cmd GROUP PATH [PATHS...]" . PHP_EOL;
			return false;
		}
	
		// On recupère le nom du groupe
		$group = $params[0];
		unset($params[0]);
		
		try {
		
			// On recupère l'API de gestion de fichier
			$api = WG::files();
		
			// On parcours les fichiers indiqués
			foreach ($params as $k => $node) {
				
				// On ne prends pas en compte les modifiers
				if (!is_int($k)) continue;
				
				// Chemin du noeud par rapport au CD
				if (substr($node, 0, 1) !== '/' && isset($_SESSION['cd'])) {
					$node = $_SESSION['cd'] . $node;
				}
				
				// On tente de modifier le groupe du noeud
				$api->setGroup($node, $group);
				
			}
		
		}
		// En cas d'erreur, on traite les exceptions
		catch (Exception $ex) {
			echo "$cmd: " . $ex->getMessage() . PHP_EOL;
			return false;
		}
	
	
	
	}
	
	/**
	 * Auto-completion pour la commande 'chown'
	 */
	function handle_chown_autocomplete($args, &$r) {
		// 1er argument = nom d'utilisateur, mais pas implémenté par sécurité
		// A partir du 2eme argument = chemins vers les cibles
		if (sizeof($args) > 2) {
			$this->autocomplete_files($args, $r);
		}
	}
	
	/**
	 * Change node's owner.
	 *
	 * @requireFlags a
	 * @allowedParams
	 * @cmdPackage Files
	 */
	function handle_chown($file, $cmd, $params, $argv) {
	
		// On vérifie droits et arguments
		if (!$this->check()) {
			return false;
		}
	
		// Pas assez d'arguments
		if (!isset($params[1])) {
			echo "Usage: $cmd OWNER PATH [PATHS...]" . PHP_EOL;
			return false;
		}
	
		// On recupère le nom de l'owner
		$owner = $params[0];
		unset($params[0]);
	
		// On recupère l'utilisateur cible
		$user = ModelManager::get('TeamMember')->getByLogin($owner, 1);
		
		// On vérifie que l'utilisateur existe bien
		if (!$user) {
			echo "User not found: $owner" . PHP_EOL;
			return false;
		}
	
		try {
	
			// On recupère l'API de gestion de fichier
			$api = WG::files();
		
			// On parcours les fichiers indiqués
			foreach ($params as $k => $node) {
		
				// On ne prends pas en compte les modifiers
				if (!is_int($k)) continue;
			
				// Chemin du noeud par rapport au CD
				if (substr($node, 0, 1) !== '/' && isset($_SESSION['cd'])) {
					$node = $_SESSION['cd'] . $node;
				}
			
				// On tente de modifier le propriétaire du noeud
				$api->setOwner($node, $owner);
		
			}
	
		}
		// En cas d'erreur, on traite les exceptions
		catch (Exception $ex) {
			echo "$cmd: " . $ex->getMessage() . PHP_EOL;
			return false;
		}

	}
	
	######################################### E X F S
	
	/**
	 * Auto-completion pour la commande 'exfs'
	 */
	function handle_exfs_autocomplete($args, &$r) {
		// TODO
	}
	
	/**
	 * Print EXFS data, and remove entries.
	 *
	 * @requireFlags Z
	 * @allowedParams help purge x
	 * @cmdPackage Files
	 */
	function handle_exfs($file, $cmd, $params, $argv) {
		
		// On vérifie droits et arguments
		if (!$this->check()) {
			return false;
		}
		
		if (isset($params['help'])) {
			echo "Usage: $cmd [--purge] [-x NODE]" . PHP_EOL;
			return true;
		}
		
		try {
	
			// On recupère l'API de gestion de fichier
			$api = WG::exfs();
			
			// S'il s'agit d'une suppression
			if (isset($params['x'])) {
				
				// On tente une suppression
				if (!$api->deleteExFsData($params['x'])) {
					echo "Unable to delete node: {$params['x']}" . PHP_EOL;
					return false;
				}
				
				return true;
				
			}

			// On parcours les fichiers indiqués
			$table = $api->getResourceData();
			
			// S'il s'agit d'une purge
			if (isset($params['purge'])) {
				
				foreach ($table as $node => $data) {
					
					$files = WG::files();
					
					if (!$files->nodeExists($node)) {
						$api->deleteExFsData($node);
						echo "Purge: $node" . PHP_EOL;
					}
					
				}
				
				return true;
					
			}
			
			// On détermine les largeurs max des données
			$length = array('node' => 5, 'owner' => 6, 'group' => 0);
			foreach ($table as $node => $data) {
				$length['node'] = max($length['node'], strlen($node));
				if (isset($data['own'])) {
					$length['owner'] = max($length['owner'], strlen($data['own']));
				}
				if (isset($data['grp'])) {
					$length['group'] = max($length['group'], strlen($data['grp']));
				}
			}
			
			echo str_pad('NODE', $length['node'] + 2);
			echo str_pad('OWNER', $length['owner'] + 2);
			echo 'GROUP' . PHP_EOL;
			echo str_repeat('-', $length['node'] + $length['owner'] + 12) . PHP_EOL;
			
			// On parcours les données pour affichage
			foreach ($table as $node => $data) {
				
				echo str_pad($node, $length['node']);
				echo "  ";
				echo str_pad($data['own'], $length['owner']);
				echo "  ";
				echo str_pad($data['grp'], $length['group']);
				
				echo PHP_EOL;
				
			}
			
			return true;
	
		}
		// En cas d'erreur, on traite les exceptions
		catch (Exception $ex) {
			echo "$cmd: " . $ex->getMessage() . PHP_EOL;
			return false;
		}
		
	}
	
	/**
	 * Autocompletion "standard" pour l'affichage de fichiers.
	 */
	function autocomplete_files($args, &$r) {
		// Debug mode
		$debugMode = false;
		// On recupère le nom de la commande
		$cmd = array_shift($args);
		// Et la dernière partie de la commande
		$arg = trim(array_pop($args));
		// Debug
		if ($debugMode) {
			$r[] = "AutoComplete CMD='$cmd' ARG='$arg'";
			$r[] = " DATE='".time()."'";
		}
		// On tente de lister les répertoires du dossier actuel
		try {
			// On recupère l'API de gestion de fichiers
			$files = WG::files();
			
			// Pour une châine vide, ou renvoi le contenu du CD
			if ($arg == '') {
				$dir = isset($_SESSION['cd']) ? $_SESSION['cd'] : '/';
				$argc = '';
			}
			// Racine
			else if ($arg == '/') {
				$dir = '/';
				$argc = '';
			}
			// Si le chemin termine par un slash, on liste tout dans
			// ce répertoire
			else if (substr($arg, -1) == '/') {
				// En prenant en compte le cd
				if (isset($_SESSION['cd']) && substr($arg, 0, 1) !== '/') {
					$dir = $files->cleanpath($_SESSION['cd'] . '/' . $arg, true);
				}
				else {
					$dir = $files->cleanpath($arg, true);
				}
				$argc = '';
			}
			// Sinon il s'agit d'un chemin partiel
			else {
				// En prenant en compte le cd
				if (isset($_SESSION['cd']) && substr($arg, 0, 1) !== '/') {
					$tmp = dirname($arg);
					if ($tmp == '.') $tmp = '';
					$dir = $files->cleanpath($_SESSION['cd'] . '/' . $tmp, true);
					if ($debugMode) {
						$r[] = " PARTIAL CD+ARG='{$_SESSION['cd']}/$tmp' ARG='$arg' DIR='$dir'";
					}
				}
				else {
					$dir = $files->cleanpath(dirname($arg), true);
				} 
				$argc = basename($arg);
			}
			
			if ($debugMode) {
				$r[] = " DIR='$dir' ARG='$arg' ARG_COMPARAISON='$argc'";
			}
			
			$length = strlen($argc);
			
			// On parcours les éléments du répertoire
			foreach ($files->ls($dir) as $item) {
				
				// On détermine s'il s'agit d'un répertoire
				$slash = $files->isDirectory("$dir/$item") ? '/' : '';
				
				// Si l'argument traité est vide, on affiche tous les items
				if ($argc == '') {
					$out = substr($item, $length);
					if ($out === false) $out = '';
					else $out .= $slash;
					if ($debugMode) {
						$r[] = " ACCEPT BY='default' ITEM='$item' RETURNS='$out'";
					}
					else {
						$r[] = $out;
					}
				}
				
				// Sinon, on compare le nom de l'item avec $argc
				else if (substr($item, 0, $length) === $argc) {
					$out = substr($item, $length);
					if ($out === false) $out = '';
					else $out .= $slash;
					if ($debugMode) {
						$r[] = " ACCEPT by='comp' ITEM='$item' RETURNS='$out'";
					}
					else {
						$r[] = $out;
					}
				}
				
				// Sinon l'item est refusé
				else if ($debugMode) {
					$r[] = " DENY ITEM='$item'";
				}
			}
			
			// Petit fix pour la fin : remplacer un unique retour vide par un slash,
			// pour terminer la completion des noms de répertoires.
			if (sizeof($r) === 1 && $r[0] === '') {
				if ($debugMode) {
					$r[] = " FIX PATH='/'";
				}
				else {
					$r = array('/');
				}
			}
		}
		catch (Exception $ex) {
			// En cas d'erreur, on ne fait tout simplement rien
			if ($debugMode) {
				$r[] = $ex->getMessage();
			}
		}
	}

}

?>