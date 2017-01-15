<?php

class Soho_CLI_Base extends Soho_CLI {
	
	/**
	 * Indique si la commande 'sudo' accepte les mots de passes transmis en clair.
	 * Note: il faut laisser activé si PHP est utilisé en CLI. Seule l'interface Web
	 * utilise le mot de passe crypté).
	 * @var boolean
	 */
	public $allowSudoWithClearPassword = true;
	
	/**
	 * Indique si le shell supporte les mots de passes en cryptés.
	 * @var boolean
	 */
	public $supportHashedPassword = true;

	/**
	 * Nom d'utilisateur par défaut pour le sudo. 
	 * @var string
	 */
	public $defaultSudoUser = 'root';
	
	/**
	 * @cmdAlias userlist
	 */
	function handle_users($file, $cmd, $params, $argv) {
		return $this->handle_userlist($file, $cmd, $params, $argv);
	}

	/**
	 * Userlisting of who's on the system.
	 *
	 * @requireFlags ua
	 * @allowedParams 
	 * @cmdPackage Users & Groups
	 */
	function handle_userlist($file, $cmd, $params, $argv) {
		if (!$this->check()) {
			return false;
		}
		$users = ModelManager::get('TeamMember')->all();
		echo "UID LOGIN        EMAIL                 LEVEL PWD FLAGS                LAST SEEN                 GROUPS" . PHP_EOL;
		echo "-----------------------------------------------------------------------------------------------------------" . PHP_EOL;
		$sysuser = WG::vars('mimo_account_username');
		foreach ($users as $user) {
			echo str_pad("{$user->id}", 4, ' ', STR_PAD_BOTH);
			echo str_pad(substr($user->get('login'), 0, 11) . ($user->get('login') == $sysuser ? '*' : ' '), 13);
			echo str_pad(substr($user->get('email'), 0, 21), 22);
			echo str_pad(substr($user->level(), 0, 4), 5, ' ', STR_PAD_LEFT) . ' ';
			echo str_pad($user->get('password') == '' ? ' NO' : 'YES', 4);
			echo str_pad(substr($user->get('flags'), 0, 20), 21);
			if ($user->last_connection < 1) {
				echo "Never                     ";
			}
			else {
				echo date('Y/m/d H:i', $user->last_connection);
				if ($user->last_medium) {
					echo str_pad(' (' . substr($user->last_medium, 0, 6) . ')', 10);
				}
				else {
					echo "          ";
				}
			}
			$groups = ModelManager::get('UserGroup')->getByUser($user->id);
			if (sizeof($groups) > 0) {
				$grp = array();
				foreach ($groups as $group) $grp[] = $group->group;
				echo implode(', ', $grp);
			}
			echo PHP_EOL;
		}
		echo "Total: " . sizeof($users) . PHP_EOL;
		return true;
	}

	/**
	 * Add a new user.
	 *
	 * @requireFlags ua
	 * @allowedParams help f flags passwd
	 * @cmdPackage Users & Groups
	 */
	function handle_adduser($file, $cmd, $params, $argv) {
		
		// Verification des arguments/flags
		if (!$this->check()) {
			return false;
		}
		
		// On charge la librairie WGCRT (si elle ne l'est pas déjà)
		require_once WG::base('inc/wgcrt.php');
		
		// Aide
		if (isset($params['help'])) {
			echo "Usage: adduser [ --flags=<FLAGS> | -f <FLAGS> ] <LOGIN> <EMAIL>" . PHP_EOL;
			return true;
		}
		
		// Verification des paramètres
		if (sizeof($params) < 2 || !isset($params[1])) {
			echo "Usage: adduser [--help] [ --flags=<FLAGS> | -f <FLAGS> ] <LOGIN> <EMAIL>" . PHP_EOL;
			return false;
		}
		if (mb_strlen($params[0]) < 3 || mb_strlen($params[0]) > 128) {
			echo "Error: the given login `{$params[0]}` is too long or too short (3-128)" . PHP_EOL;
			return false;
		}
		if (!preg_match('/^[a-z]{3,}$/i', $params[0])) {
			echo "Error: invalid login name." . PHP_EOL;
			return false;
		}
		if (@!eregi("^[a-z0-9_\+-]+(\.[a-z0-9_\+-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*\.([a-z]{2,4})$", $params[1])) {
			echo "Error: invalid email given `{$params[1]}`" . PHP_EOL;
			return false;
		}
		
		// Flags
		if (isset($params['f'])) $flags = $params['f'];
		else if (isset($params['flags'])) $flags = $params['flags'];
		else $flags = self::defaultFlags();
		
		// Check flags : on ne peut donner des flags de niveau > ou = à son propre niveau
		if (WG::user()->level() <= TeamMember::flaglevel($flags)) {
			echo "Error: you are not allowed to give `$flags` flags" . PHP_EOL;
			return false;
		}

		// Si le mot de passe n'est pas indiqué, on renvoi le code d'entrée de mot de passe
		if (!isset($params['passwd'])) {
			echo "Enter user password:" . PHP_EOL;
			return self::INPUT_PWD;
		}
		
		$password = $params['passwd'];
		
		if (mb_strlen($password) < 4 || mb_strlen($password) > 128) {
			echo "Error: the given password is too long or too short (4-128)" . PHP_EOL;
			return false;
		}
		
		// Create user
		$model = ModelManager::get('TeamMember');
		$new = $model->new
			->set('login', $params[0])
			->set('name', ucfirst($params[0]))
			->set('email', $params[1])
			->set('flags', $flags)
			->set('thumb', 'default.png')
			->set('color', '#aaa')
			->set('last_connection', 0);
		
		// Password
		echo "Encrypt password...                                                       " . $this->ok() . PHP_EOL;
		$new->set('password', sha1($params[0] . ':' . $password));
		//echo "$params[0]:$password\n";
		
		// API key
		echo "Generate user API key...                                                  " . $this->ok() . PHP_EOL;
		$new->set('apikey', WGCRT_Session::randomStr(48));
		
		// Save user
		echo "Create user entry ...                                                     ";
		try {
			$new->save();
			echo $this->ok() . PHP_EOL;
		}
		catch (Exception $ex) {
			echo $this->failure() . PHP_EOL;
			echo ' ' . $ex->getMessage() . PHP_EOL;
			return false;
		}

		// Home directory
		if (WG::module('files')) {
			$dir = WG::vars('files_folder');
			echo "Create home directory ...                                                 ";
			if (!is_dir($dir)) {
				echo $this->failure() . PHP_EOL;
				return false;
			}
			$dir = realpath($dir) . '/home/' . $params[0];
			if (is_dir($dir)) {
				echo $this->warn('SKIPPED') . PHP_EOL;
			}
			else if (!mkdir($dir, 0777, true)) {
				echo $this->failure() . PHP_EOL;
				return false;
			}
			else {
				// On met l'utilisateur en owner de son propre répertoire
				try {
					$node = '/home/' . $params[0] . '/';
					$api = WG::files();
					$api->setOwner($node, $params[0]);
					$api->setGroup($node, '-');
					echo $this->ok() . PHP_EOL;
				}
				catch (Exception $ex) {
					echo $this->warn('SETOWN ERROR') . PHP_EOL;
				}
			}
		}
		
		echo "Done." . PHP_EOL;
		return true;
	}

	/**
	 * Remove users from the system.
	 *
	 * @requireFlags ua
	 * @allowedParams help ignorehome
	 * @cmdPackage Users & Groups
	 */
	function handle_deluser($file, $cmd, $params, $argv) {
		
		// Verification des arguments/flags
		if (!$this->check()) {
			return false;
		}
		
		// Aide
		if (isset($params['help'])) {
			echo "Usage: deluser [--ignorehome=yes|no] <USER> [USERS..]" . PHP_EOL;
			return true;
		}
		
		// Check arguments
		if (sizeof($params) < 1 || !isset($params[0])) {
			echo "Usage: deluser [--help] [--ignorehome=yes|no] <USER> [USERS..]" . PHP_EOL;
			return false;
		}

		// Fonction de suppression recursive
		if (!function_exists('rrmdir')) {
			function rrmdir($dir) {
				if (is_dir($dir)) {
					$objects = scandir($dir);
					foreach ($objects as $object) {
						if ($object == '.' || $object == '..') continue;
						$path = "$dir/$object";
						if (filetype($path) == 'dir') {
							rrmdir($path);
						}
						else {
							unlink($path);
						}
					}
					reset($objects);
					rmdir($dir);
				}
			}
		}
		
		/*echo "deluser: command disabled" . PHP_EOL;
		return false;*/
		
		// On a besoin de cet API
		try {
			$api = WG::exfs();
		}
		catch (Exception $ex) {
			echo "$cmd: " . $ex->getMessage() . PHP_EOL;
			return false;
		}
		
		// Recupération du model utilisé pour les user accounts
		$model = ModelManager::get('TeamMember');
		
		// On parcours les noms d'utilisateurs saisis en paramètres
		foreach ($params as $k => $username) {
			
			// On ignore les modifiers
			if (!is_int($k)) continue;
			
			// On recupère le compte utilisateur
			$user = $model->getByLogin($username, 1);
			
			// User not found
			if (!$user) {
				echo "Error: user `$username` not found." . PHP_EOL;
				return false;
			}

			// On recupère l'user actuel qui a lancé la commande
			$self = WG::user();
			
			// Check self delete
			if ($user->id === $self->id) {
				echo "deluser $username: not allowed, you can't delete yourself." . PHP_EOL;
				return false;
			}
			
			// Check user level : on ne peut supprimer un utilisateur de niveau supérieur
			// ou égal au niveau de l'utilisateur actuel
			if ($user->level() >= $self->level()) {
				echo "deluser $username: you are not allowed to delete this user." . PHP_EOL;
				return false;
			}
			
			// System user
			if ($user->get('login') === WG::vars('mimo_account_username')) {
				echo "deluser $username: you can't delete the system user." . PHP_EOL;
				return false;
			}
			
			// Mode demo
			$demo = false;
			
			// Delete sessions
			echo "Delete sessions...                         " . $this->ok() . PHP_EOL;
			if (!$demo) {
				foreach ($user->getSessions(true) as $session) {
					$session->unsudo()->write();
				}
				foreach ($user->getSessions(false) as $session) {
					$session->destroy();
				}
			}
			
			// Delete home directory
			if (!isset($params['ignorehome']) || $params['ignorehome'] != 'yes') {
				if (is_dir($user->getAbsoluteUserFolder())) {
					echo "Delete user home directory...              " . $this->ok() . PHP_EOL;
					if (!$demo) {
						rrmdir($user->getAbsoluteUserFolder());
					}
					echo "Delete EXFS data for home directory...     " . $this->ok() . PHP_EOL;
					if (!$demo) {
						$api->deleteExFsData($user->getUserFolder());
					}
				}
				else {
					echo "Delete user home directory...              " . $this->warn("Not found") . PHP_EOL;
				}
			}

			// On enregistre l'ID du l'utilisateur pour quand le model sera supprimé
			$uid = $user->id;
			
			// Delete user	
			if (!$demo && !$user->delete()) {
				echo "Delete user record ...                     " . $this->failure() . PHP_EOL;
				return false;
			}
			else {
				echo "Delete user record ...                     " . $this->ok() . PHP_EOL;
			}
			
			// Delete teammember_extra
			echo "Delete user extra data...                  " . $this->ok() . PHP_EOL;
			if (!$demo) {
				ModelManager::get('TeamMember_Extra')->deleteWhere(array('user' => $uid));
			}
			
			// Delete groups
			echo "Delete user groups...                      " . $this->ok() . PHP_EOL;
			if (!$demo) {
				ModelManager::get('UserGroup')->deleteWhere(array('user' => $uid));
			}
			
		}
		return true;
	}
	
	/**
	 * Auto-completion pour la commande 'addgroup'
	 */
	function handle_addgroup_autocomplete($args, &$r) {
		// 1er argument = nom de groupe
		if (sizeof($args) === 2) {
			$this->autocomplete_groups($args[1], $r);
		}
	}

	/**
	 * Add a group to given users.
	 *
	 * @requireFlags ua
	 * @allowedParams help
	 * @cmdPackage Users & Groups
	 */
	function handle_addgroup($file, $cmd, $params, $argv) {
		
		// Verification des arguments/flags
		if (!$this->check()) {
			return false;
		}
		
		// Aide
		if (isset($params['help'])) {
			echo "Usage: addgroup <GROUP> <USER> [USERS..]" . PHP_EOL;
			return true;
		}
		
		// Check parameters
		if (sizeof($params) < 2 || !isset($params[1])) {
			echo "Usage: addgroup [--help] <GROUP> <USER> [USERS..]" . PHP_EOL;
			return false;
		}
		
		// On va avoir besoin de ce model
		$model = ModelManager::get('UserGroup');
		
		// Nom du groupe
		$group = $params[0];
		unset($params[0]);
		
		// On parcours les noms d'utilisateurs
		foreach ($params as $k => $username) {
			
			// On ignore les modifiers
			if (!is_int($k)) continue;
			
			// Pour l'utilisateur système c'est même pas la peine d'essayer
			if ($username === WG::vars('mimo_account_username')) {
				echo "addgroup $username: can't modifiy system user." . PHP_EOL;
				return false;
			}
			
			/// On recupère l'utilisateur associé au login
			$user = ModelManager::get('TeamMember')->getByLogin($username, 1);
			
			// L'utilisateur n'existe pas
			if (!$user) {
				echo "addgroup $username: user `$username` not found." . PHP_EOL;
				return false;;
			}
			
			// On vérifie les niveaux: on ne peut pas modifier les groupes d'un utilisateur
			// de niveau > ou = au sien.
			if ($user->level() >= WG::user()->level()) {
				echo "addgroup $username: you are not allowed to change groups of this user." . PHP_EOL;
				return false;
			}
			
			// On fait une requête pour savoir si cet utilisateur est déjà dans ce groupe
			$groups = $model->get(array(
				'user' => $user->get('id'),
				'group' => $group
			));
			
			// L'utilisateur est déjà dans le groupe
			if (sizeof($groups) > 0) {
				echo "User `$username` is allready in group `$group`" . PHP_EOL;
				continue;
			}
			
			// Enregistrement
			if (!$model->new->set('user', $user->id)->set('group', $group)->save()) {
				echo "Failure! Unable to add user `$username` in group `$group`" . PHP_EOL;
				return false;
			}
			echo "Adding user `$username` to group `$group` ..." . PHP_EOL;
			
		}
		return true;
	}

	
	/**
	 * Log out.
	 *
	 * @requireFlags u
	 * @allowedParams 
	 * @cmdPackage Users & Groups
	 */
	function handle_logout($file, $cmd, $params, $argv) {
		echo "Bye, bye." . PHP_EOL;
		WGCRT_Session::startedSession()->logout();
		return true;
	}

	/**
	 * @cmdAlias chpass
	 */
	function handle_passwd($file, $cmd, $params, $argv) {
		return $this->handle_chpass($file, $cmd, $params, $argv);
	}
	
	/**
	 * Change user passwords.
	 *
	 * @requireFlags u
	 * @allowedParams help d passwd
	 * @cmdPackage Users & Groups
	 */
	function handle_chpass($file, $cmd, $params, $argv) {
		
		// Verification des arguments/flags
		if (!$this->check()) {
			return false;
		}
		
		// Aide
		if (isset($params['help']) || !isset($params[0])) {
			echo "Usage: chpass <USER> [REALM]" . PHP_EOL;
			echo "Usage: chpass -d <USER> [REALM]" . PHP_EOL;
			echo " The -d flag deletes the password." . PHP_EOL;
			echo " The REALM parameter is for htdigest passwords." . PHP_EOL;
			return true;
		}
		
		// Delete password
		if (isset($params['d'])) {
			
			// Fix
			$username = $params['d'];

			// Un peu spécial : -d ici n'est pas utilisé comme modifier mais comme flag,
			// donc il doit avoir un contenu (c-à-d être suivi par le nom d'utilisateur)
			// et pas simplement être là.
			if (!is_string($username)) {
				echo "Usage: chpass -d <USER> [REALM]" . PHP_EOL;
				return false;
			}
			
			// On recupère l'utilisateur cible
			$user = ModelManager::get('TeamMember')->getByLogin($username, 1);

			
			// Delete realm password
			if (isset($params[0])) {
				
				return $this->chpass_delete_realm($user, $username, $params[0]);
				
			}
			
			// Delete account password
			else {

				return $this->chpass_delete_user($user, $username);
				
			}
			
		}
		
		// Edit password
		else {
			
			// Recupération du nom d'utilisateur cible
			$username = $params[0];

			// Check target user
			$user = ModelManager::get('TeamMember')->getByLogin($username, 1);
			if (!$user) {
				echo "Error: user `$username` not found" . PHP_EOL;
				return false;
			}
			
			// Check level : on ne peut pas modifier le mot de passe d'un utilisateur de
			// niveau > ou = à son propre niveau, sauf s'il s'agit de soit même.
			$self = WG::user();
			if ($user->level() >= $self->level() && $user->get('id') !== $self->get('id')) {
				echo "Error: you are not allowed to change this user passwords" . PHP_EOL;
				return false;
			}
			
			// Traitement spécifique pour le compte système : la modification du mot de passe
			// n'est tout simplement pas possible.
			if ($username === WG::vars('mimo_account_username')) {
				echo "Error: you are not allowed to change system user passwords" . PHP_EOL;
				return false;
			}
			
			// Si le mot de passe n'est pas renseigné, on renvoi une demande
			if (!isset($params['passwd'])) {
				echo "Enter user password:" . PHP_EOL;
				return self::INPUT_PWD;
			}
			
			// Check password
			if (strlen($params['passwd']) < 4 || strlen($params['passwd']) > 128) {
				echo "Error: the given password is too long or too short (4-128)" . PHP_EOL;
				return false;
			}
			
			// Edit realm password
			if (isset($params[1])) {
				
				return $this->chpass_edit_realm($user, $params[1], $params['passwd']);
				
			}
			
			// Edit account password
			else {
				
				return $this->chpass_edit_user($user, $username, $params['passwd']);
				
			}
			
		}

	}
	
	private function chpass_delete_realm(Moodel $user=null, $username, $realm) {
		
		// Traitement spécifique pour le compte système : la modification du mot de passe
		// n'est tout simplement pas possible.
		if ($username === WG::vars('mimo_account_username')) {
			echo "Error: you are not allowed to delete system user passwords" . PHP_EOL;
			return false;
		} 
		
		// Si l'utilisateur existe, on applique le dispositif de sécurité
		// S'il n'existe plus, on laisse faire car il faut pouvoir supprimer les anciens mdp
		if ($user != null) {
			// Check level : on ne peut pas supprimer le mot de passe d'un utilisateur de
			// niveau > ou = à son propre niveau, sauf s'il s'agit de soit même.
			$self = WG::user();
			if ($user->level() >= $self->level() && $user->get('id') !== $self->get('id')) {
				echo "Error: you are not allowed to delete this user passwords" . PHP_EOL;
				return false;
			}
		}
		
		// On recupère les données du htdigest
		$info = WG::htdigest($realm, false);
		
		// Ainsi que les entrées dans le fichier de mot de passe
		$entries = WG::htdigest($realm, true);
		
		// Le htdigest n'existe pas
		if (!is_array($entries)) {
			echo "Error: digest file for realm `$realm` not found." . PHP_EOL;
			echo " Use `digest` to list valid realm." . PHP_EOL;
			return false;
		}
		
		// On parcours la liste des entrées
		foreach ($entries as $k => $v) {
			// Suppression de toutes les entrées de cet utilisateur
			if ($v['user'] == $username) {
				echo "Delete user `$username` with realm `{$v['realm']}` ..." . PHP_EOL;
				unset($entries[$k]);
				continue;
			}
			// Réécriture
			$entries[$k] = implode(':', $v);
		}
		
		// Enregistrement du fichier
		if (file_put_contents(WG::base($info['file']), implode("\n", $entries)) === false) {
			echo "Error: unable to save htdigest file {$info['file']}" . PHP_EOL;
			return false;
		}
		
		return true;
	}
	
	private function chpass_delete_user(Moodel $user = null, $username) {
		if (!$user) {
			echo "Error: user `$username` not found." . PHP_EOL;
			return false;
		}
		echo "Error: not supported." . PHP_EOL;
		return false;
	}
	
	private function chpass_edit_realm(Moodel $user, $realm, $password) {
		
		// On va avoir besoin de ça
		$username = $user->get('login');
		
		// On recupère les données du htdigest
		$info = WG::htdigest($realm, false);
		
		// Ainsi que les entrées dans le fichier de mot de passe
		$entries = WG::htdigest($realm, true);
		
		// Le htdigest n'existe pas
		if ($info === null) {
			echo "Error: htdigest with realm `$realm` not found." . PHP_EOL;
			echo " Use `digest` to list valid realm." . PHP_EOL;
			return false;
		}
		
		// Verification des flags
		if (isset($info['requireFlags'])) {
			if (!WG::checkFlags($info['requireFlags'])) {
				echo "Error: you are not allowed to display this realm" . PHP_EOL;
				return false;
			}
		}

		// Verification des groupes
		if (isset($info['requireGroup'])) {
			if (!WG::checkGroups($info['requireGroup'])) {
				echo "Error: you are not allowed to display this realm" . PHP_EOL;
				return false;
			}
		}
	
		// On fabrique le hash MD5 du fichier htdigest
		// @see http://www.freebsdwiki.net/index.php/Apache,_Digest_Authentication
		$hash = md5($username . ':' . $realm . ':' . $password);
		
		// Ce flag servira à noter si le hash existe déjà dans le fichier
		$found = false;
		
		// On parcours les entrées
		foreach ($entries as $k => $entry) {
			// Le hash existe dans le fichier, on le modifie
			if ($entry['user'] == $username && $entry['realm'] == $realm) {
				$found = true;
				$entries[$k]['hash'] = $hash;
				break;
			}
		}

		// Si le hash n'a pas été trouvé, on l'ajoute dans les entrées
		if (!$found) {
			$entries[] = array(
				'user' => $username,
				'realm' => $realm,
				'hash' => $hash
			);
		}
		
		// On réécrit les entrées pour l'enregistrement
		foreach ($entries as $k => $entry) {
			$entries[$k] = implode(':', $entry);
		}
		
		// Enregistrement du fichier
		if (file_put_contents(WG::base($info['file']), implode("\n", $entries)) === false) {
			echo "Error: unable to save htdigest file {$info['file']}" . PHP_EOL;
			return false;
		}
		
		echo "Password for user `$username` in htdigest file `$realm` changed." . PHP_EOL;
		return true;
	}
	
	private function chpass_edit_user(Moodel $user, $username, $password) {
		try {
			$user
				->set('password', sha1($username . ':' . $password))
				->save();
			echo "Password for user `$username` changed." . PHP_EOL;
			return true;
		}
		catch (Exception $ex) {
			echo "Failure: unable to edit user account!" . PHP_EOL;
			echo ' ' . $ex->getMessage() . PHP_EOL;
			return false;
		}
	}
	
	/**
	 * Auto-completion pour la commande 'delgroup'
	 */
	function handle_delgroup_autocomplete($args, &$r) {
		// 1er argument = nom de groupe
		if (sizeof($args) === 2) {
			$this->autocomplete_groups($args[1], $r);
		}
	}

	/**
	 * Remove groups from the system.
	 *
	 * @requireFlags ua
	 * @allowedParams help
	 * @cmdPackage Users & Groups
	 */
	function handle_delgroup($file, $cmd, $params, $argv) {
		
		// Verification des arguments/flags
		if (!$this->check()) {
			return false;
		}
		
		// Aide
		if (isset($params['help'])) {
			echo "Usage: delgroup GROUP [USER..]" . PHP_EOL;
			return true;
		}
		
		// Check arguments
		if (sizeof($params) < 1 || !isset($params[0])) {
			echo "Usage: delgroup [--help] GROUP [USER..]" . PHP_EOL;
			return false;
		}
		
		// Nom du groupe
		$groupname = $params[0];
		unset($params[0]);
		
		// Appliquer la suppression à tous les utilisateurs
		if (!isset($params[1])) {
			// TODO On ne fait aucune vérification ici !
			/*$count = ModelManager::get('UserGroup')->deleteWhere(array('group' => $groupname));
			echo "Delete group `$groupname` : $count user(s)" . PHP_EOL;*/
			echo "Global group deletion: not suported yet...";
			return true;
		}
		
		// Appliquer la suppression à une liste d'utilisateurs
		else {
			
			// On va avoir besoin de ça
			$model = ModelManager::get('TeamMember');

			// On parcours la liste des noms d'utilisateurs
			foreach ($params as $k => $username) {
				
				// On évite les modifiers
				if (!is_int($k)) continue;
				
				// Pour l'utilisateur système c'est même pas la peine d'essayer
				if ($username === WG::vars('mimo_account_username')) {
					echo "Error: can't modifiy system user." . PHP_EOL;
					return false;
				}
				
				// On recupère l'utilisateur cible
				$user = $model->getByLogin($username, 1);

				// L'utilisateur n'existe pas
				if (!$user) {
					echo "Error: user `$username` doesn't exists" . PHP_EOL;
					continue;
				}

				// On vérifie les niveaux: on ne peut pas modifier les groupes d'un utilisateur
				// de niveau > ou = au sien.
				if ($user->level() >= WG::user()->level()) {
					echo "Error: you are not allowed to change groups of this user." . PHP_EOL;
					return false;
				}

				// On fait la requête à la bdd
				$count = ModelManager::get('UserGroup')->deleteWhere(array(
					'group' => $groupname,
					'user' => $user->id
				));
				
				// Affichage du résultat
				if ($count > 0) {
					echo "Delete group `$groupname` for user `$username` ..." . PHP_EOL;
				}
				else {
					echo "User `$username` is no in group `$groupname`" . PHP_EOL;
				}
				
			}
			
			return true;
		}
	}

	/**
	 * @cmdAlias session
	 */
	function handle_who($file, $cmd, $params, $argv) {
		return $this->handle_session($file, $cmd, $params, $argv);
	}
	
	/**
	 * List of current session on the system.
	 *
	 * @requireFlags a
	 * @allowedParams u help
	 * @cmdPackage Security
	 */
	function handle_session($file, $cmd, $params, $argv) {
		
		// Verification des arguments/flags
		if (!$this->check()) {
			return false;
		}
		
		// Aide
		if (isset($params['help'])) {
			echo "Usage: $cmd" . PHP_EOL;
			echo "Usage: $cmd kill SID" . PHP_EOL;
			echo "Usage: $cmd kill -u USER" . PHP_EOL;
			return true;
		}
		
		// Action
		if (isset($params[0])) {
			
			switch ($params[0]) {
				
				// KILL
				case 'kill' :
					
					if (!isset($params[1]) && !isset($params['u'])) {
						echo "Usage: $cmd kill SID" . PHP_EOL;
						echo "Usage: $cmd kill -u USER" . PHP_EOL;
						return false;
					}
					
					// Kill -u USER
					if (isset($params['u'])) {
						
						// On recupère le model de l'utilisateur
						$user = ModelManager::get('TeamMember')->getByLogin($params['u'], 1);
						
						// Traitement d'erreur
						if (!$user) {
							echo "$cmd: user `{$params['u']}` not found" . PHP_EOL;
							return false;
						}
						
						// On s'assure qu'on ai le droit de supprimer la session
						// On ne peut supprimer la session QUE des utilisateur ayant niveau < au sien 
						if ($user->level() >= WG::user()->level()) {
							echo "$cmd: you are not allowed to kill these sessions." . PHP_EOL;
							return false;
						}
						
						// On recupère les sessions de l'utilisateur
						$sessions = WGCRT_Session::getUserSessions($user, true);
						
						// On supprime les sessions
						foreach ($sessions as $session) {
							echo "Session " . $session->getSID() . " killed." . PHP_EOL;
							$session->destroy();
						}
						
						return true;
					}
						
					// On recupère la session
					$sid = $params[1];
					$session = WGCRT_Session::getById($sid);
					
					// Session introuvable
					if (!$session) {
						echo "$cmd: session `$sid` not found." . PHP_EOL;
						return false;
					}
						
					// Si la session est loggée, on tester les levels
					if ($session->isLogged()) {
						// Verification de level : on ne peut détruire la session d'un utilisateur
						// ayant un niveau > ou = au sien. On se base sur le niveau réel de l'utilisateur,
						// le sudo ne compte pas.
						if ($session->getRealUser()->level() >= WG::user()->level()) {
							echo "$cmd: you are not allowed to kill this session." . PHP_EOL;
							return false;
						}
					}
						
					$session->destroy();
					return true;
					break;
					
				default :
					echo "$cmd: action '{$params[0]}' not supported" . PHP_EOL;
					return false;
				
			}
			
		}
		

		
		
		
		// List
		$c = 0;
		echo "SID  TYPE   USER              HOST                               USER AGENT             QOP     LAST REQUEST" . PHP_EOL;
		echo "----------------------------------------------------------------------------------------------------------------" . PHP_EOL;
		foreach (WGCRT_Session::getSessions() as $session) {
			$id = $session->getSID();
			$user = $session->getRealUser();
			if (isset($params['u'])) {
				if ($user) {
					if ($user->get('login') != $params['u']) continue;
				}
				else continue;
			}
			echo str_pad($id === 0 ? ':' : "$id", 4) .' ';
			echo str_pad(substr($session->getType(), 0, 6), 7);
			if ($user) {
				$user = $user->get('login');
				if ($user !== $session->getUser()->get('login')) {
					$user .= ' (' . $session->getUser()->get('login') . ')';
				}
			}
			echo str_pad(substr($user, 0, 17), 18);
			
			echo str_pad(substr($session->getHostName(), 0, 34), 35);

			$agent = $session->getUserAgent();
			if (($pos = strpos($agent, 'MSIE')) > 0) {
				$agent = substr($agent, $pos);
				$agent = substr($agent, 0, strpos($agent, ';'));
			}
			else if (is_array($browser = WGCRT_Session::get_browser_versions($agent))) {
				foreach (array('Fennec', 'Firefox', 'Chromium', 'Chrome', 'Safari', 'Mozilla') as $aname) {
					if (isset($browser[$aname])) {
						$agent = $aname . ' ' . $browser[$aname];
						break;
					}
				}
			}
			echo str_pad(substr($agent, 0, 22), 23);			
			echo str_pad(substr($session->getSecurityTag(), 0, 7), 8);
			echo WG::rdate($session->getLastRequest());
			echo PHP_EOL;
			$c++;
		}
		echo "Total: $c" . PHP_EOL;
		return true;
		
	}

	/**
	 * @cmdAlias grouplist
	 */
	function handle_groups($file, $cmd, $params, $argv) {
		return $this->handle_grouplist($file, $cmd, $params, $argv);
	}

	/**
	 * List of existing groups.
	 *
	 * @requireFlags u
	 * @allowedParams
	 * @cmdPackage Users & Groups
	 */
	function handle_grouplist($file, $cmd, $params, $argv) {
		if (!$this->check()) {
			return false;
		}
		$model = ModelManager::get('TeamMember');
		$groups = ModelManager::get('UserGroup')->all();
		$out = array();
		foreach ($groups as $group) {
			if (!isset($out[$group->group])) {
				$out[$group->group] = array();
			}
			$user = $model->getById($group->user->id, 1);
			$out[$group->group][] = $user->login;
			unset($user);
		}
		unset($groups);
		echo "GROUP                USERS" . PHP_EOL;
		echo "------------------------------" . PHP_EOL;
		foreach ($out as $group => $users) {
			echo str_pad(substr($group, 0, 20), 21);
			echo implode(', ', $users);
			echo PHP_EOL;
		}
		echo "Total: " . sizeof($out) . PHP_EOL;
		return true;
	}

	/**
	 * Génére un trousseau de clés.
	 * 
	 * @param string $user
	 * @param string $realm
	 * @param int $length Nombre de caractères des clés.
	 * @param int $size Taille du trousseau (nombre de clés).
	 * @return mixed[][] Un tableau avec deux indices, en premier les clés en clair
	 *  et en second les clés cryptées.
	 */
	public static function generateKeyringKeys($user, $realm, $length, $size) {
		
		// Caractères autorisés : uniquement les caractères qui ne se ressemblent pas trop visuellement
		$chars = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b','c','d', 'e', 'f', 'g', 'h',
				'i', 'j', 'k', 'm', 'o', 'p', 'r', 's', 'u', 'w', 'x', 'y', 'z');
		
		// Taille de l'échantillon
		$l = sizeof($chars);
		
		// On vérifie que le nombre de clé soit possible sans faire de colisions
		if ($size > pow($l, $length)) {
			throw new Exception("Unable to generate $size keys with $length chars");
		}
		
		// Clés stoquées en clair
		$clear = array();
		
		// Clés stoquées cryptées
		$crypted = array();
		
		// Boucle de génération des clés
		while ($size-- > 0) {
			
			// La clé générée
			$key = '';
			
			// Compteur de caractères
			$c = $length;
			
			// Boucle de génération des caractères
			while ($c-- > 0) {
				
				// Génération d'un caractère
				$key .= $chars[rand(1, $l) - 1];
				
			}
			
			// On s'assure que la clé n'existe pas déjà
			if (in_array($key, $clear)) {
				$size++;
				continue;
			}
			
			// La clé est générée, on va l'enregistrer
			$clear[] = $key;
			
			// Et on l'enregistre en crypté
			$crypted[] = sha1("$user:$realm:$key");
			
		}
		
		// On retourne les données de résultats
		return array($clear, $crypted);		
		
	}
	
	public static function generateKeyring($user, $realm, $length = 6, $size = 256) {
		
		// On génére les clés, et on obtient deux tableaux avec les clés
		// en clair et en cryptées
		list($clear, $crypted) = self::generateKeyringKeys($user, $realm, $length, $size);
		
		// On fabrique le contenu du fichier en clair
		$plain = "-------------------------------- KEYRING FILE FOR ". strtoupper(WG::vars('appName')) ." --------------------------------";
		$l1 = strlen($plain);
		$l2 = floor(($l1 - 6) / 2);
		$plain .= "\n USER: $user\n REALM: $realm\n HOME: " . WG::vars('appurl')."\n GENERATED: " . date('r', $_SERVER['REQUEST_TIME']);
		$plain .= "\n" . str_repeat('-', $l2) . ' KEYS ' . str_repeat('-', $l2);
		$plain .= str_repeat('-', ($l1 - ($l2 * 2 + 6))) . "\n";
		$l3 = strlen("$size"); // taille des curseurs
		$l4 = $l3 + 2 + 5; // taille d'une entrée "CURSEUR. CLE"
		$c = 0;
		foreach ($clear as $k => $v) {
			$c += $l4;
			if ($c > $l1) {
				$plain .= "\n";
				$c = $l4;
			}
			if ($k > 0) {
				$plain .= str_pad($k, $l3, ' ', 0);
			}
			else {
				$plain .= str_repeat(' ', $l3 - 1) . '0';
			}
			
			$plain .= ") $v ";
		}
		$plain .= "\n" . str_repeat('-', $l1);
		
		// On fabrique le coffre contenant les clés
		$lock = array(
			'user' => $user,
			'realm' => $realm,
			'cursor' => 0,
			'lasttime' => 0,
			'keys' => $crypted
		);
		
		// On renvoi les deux éléments
		return array($plain, $lock);
		
	}
	
	/**
	 * Auto-completion pour la commande 'keyring'
	 */
	function handle_keyring_autocomplete($args, &$r) {
		// 1er argument = sous-commandes
		if (sizeof($args) === 2) {
			$this->autocompleteFilter($args[1], array('add', 'list', 'regenerate'), $r);
		}
	}
	
	/**
	 * Manage user's keyrings
	 * 
	 * @requireFlags a
	 * @allowedParams help
	 * @cmdPackage Security
	 */
	function handle_keyring($file, $cmd, $params, $argv) {
		
		// Aide
		if (isset($params['help']) || (sizeof($params) < 1 || !isset($params[0]))) {
			echo "Usage: $cmd ACTION [PARAMS..]" . PHP_EOL;
			echo "Available actions are:" . PHP_EOL;
			echo "  $cmd " . $this->bold('add') . " [--email=ON|OFF] [--file=ON|OFF] USER REALM" . PHP_EOL;
			echo "  $cmd " . $this->bold('list') . PHP_EOL;
			echo "  $cmd " . $this->bold('regenerate') . " [--email=ON|OFF] [--file=ON|OFF] USER REALM" . PHP_EOL;
			echo "If 'email' modifier is set to ON, the clear passwords will be sent to user's mail account. Default is: OFF." . PHP_EOL;
			echo "The 'file' modifier let the clear passwords stored in a plain text file in your home directory. Default is: ON." . PHP_EOL;
			return isset($params['help']);
		}
		
		// Taille du trousseau (nombre de clés)
		$size = 600;
		
		// Action
		$action = strtolower($params[0]);
		unset($params[0]);
		
		// Switch action
		switch ($action) {
			
			// ADD, REGENERATE
			case 'add' :
			case 'regenerate' :
				
				// Check arguments
				if (sizeof($params) < 2 || !isset($params[2])) {
					echo "Usage: $cmd add USER REALM" . PHP_EOL;
					return false;
				}
				
				// User
				$user = $params[1];
				unset($params[1]);
				
				// Check user
				$account = ModelManager::get('TeamMember')->getByLogin($user, 1);
				if (!is_object($account)) {
					echo "User not found: $user" . PHP_EOL;
					return false;
				}
				
				// Check rights
				if ($user->level() >= WG::user()->level()) {
					echo "You are not allowed to this keyring." . PHP_EOL;
					return false;
				}
				
				// Realm
				$realm = array();
				foreach ($params as $k => $v) {
					if (is_int($k) && !empty($v)) $realm[] = $v;
				}
				$realm = implode(' ', $realm);
				
				// Config
				$sendEmail = (isset($params['email']) && strtoupper($params['email']) == 'ON');
				$savePlainFile = !(isset($params['file']) && strtoupper($params['file']) == 'OFF');
				
				// File ID and path
				$fileid = md5("$user:$realm");
				$filepath = WG::base("data/keyrings/$fileid.kr");
				
				// Check file
				if ($action == 'add' && file_exists($filepath)) {
					echo "$cmd: this keyring allready exists." . PHP_EOL;
					echo "Use '$cmd regenerate $user' instead." . PHP_EOL;
					return false;
				}
				else if ($action == 'regenerate' && !file_exists($filepath)) {
					echo "$cmd: keyring file for user '$user' and realm '$realm' doesn't exists." . PHP_EOL;
					echo "Use '$cmd add $user' to create a keyring for this user." . PHP_EOL;
					return false;
				}

				// UI
				echo "Generating $size passwords..." . PHP_EOL;
				
				// Check config
				if (!$sendEmail && !$savePlainFile) {
					echo $this->orange($this->bold("Warning:")) . " clear passwords will not be saved." . PHP_EOL;
				}
				
				// On génére les clés
				list($clear, $lock) = self::generateKeyring($user, $realm, 5, $size);
				
				// Enregistrement du fichier coffre
				if (!file_put_contents($filepath, json_encode($lock))) {
					echo "$cmd: unable to save lock file." . PHP_EOL;
					return false;
				}
				
				// La génération est finie
				echo "Keyring has been generated." . PHP_EOL;
				
				// Sauvegarde du fichier avec les clés en clairs
				if ($savePlainFile) {
					
					// Chemin vers le fichier de sortie des clés en clairs
					$clearfile = '/home/' . WG::user()->get('login') . "/KEYS-$user.$fileid";
					$clearpath = WG::vars('files_folder') . $clearfile;
					
					// Enregistrement du fichier en clair
					if (!file_put_contents($clearpath, $clear)) {
						echo "$cmd: unable to save clear password file." . PHP_EOL;
						return false;
					}
					
					// UI
					echo "** Private passwords file has been saved in: " . $this->bold($clearfile) . PHP_EOL;
					echo $this->red($this->bold("Attention:")) . " this file contains clear passwords, YOU MUST get it, print it then DELETE it after!" . PHP_EOL;
				}
				
				// Send email
				if ($sendEmail) {
					
					echo "** Sending clear passwords to email address " . $this->underline($account->get('email')) . PHP_EOL;
					
					$email = array(
						'Dear,',
						'We send you this email because your keyring for <em>'.htmlspecialchars($realm).'</em> has changed.',
						'The following are your passwords. <strong style="color:red">YOU MUST PRINT IT THEN DELETE IT AFTER! DON\'T KEEP THIS MESSAGE IN YOUR MAILBOX!</strong>',
						'',
						htmlspecialchars(WG::vars('appOwner'))
					);
					
					// Push mail
					ModelManager::get('EmailCronTask')->new
						->set('to', $account->get('email'))
						->set('from', WG::vars('contact_email'))
						->set('creation', $_SERVER['REQUEST_TIME'])
						->set('title', "Your keyring has changed: $realm")
						->set('contents', '<html><p>'.implode('</p><p>', $email).'</p><pre>' . $clear . '</pre></html>')
						->save();
					
				}
				
				return true;
			
			// LIST
			case 'list' :
				
				$files = @glob(WG::base('data/keyrings/*.kr'));
				
				if (!is_array($files)) {
					echo "$cmd: no keyring directory" . PHP_EOL;
					return false;
				}

				echo "FILE            USER       REALM                         CURRENT LEFT    TS" . PHP_EOL;
				echo "-------------------------------------------------------------------------------" . PHP_EOL;
				foreach ($files as $file) {
					
					// Liens symboliques
					if ($file == '.' || $file == '..') continue;
					
					// Nom de fichier
					echo str_pad(substr(basename($file), 0, 15), 16);
					
					// Ouverture du fichier
					$lock = json_decode(file_get_contents($file), true);
					
					// Erreur de désérialisation
					if (!is_array($lock)) {
						echo 'I/O Error' . PHP_EOL;
						continue;
					}
					
					// Nom d'utilisateur
					echo str_pad(substr($lock['user'], 0, 10), 11);
					
					// Realm
					echo str_pad(substr($lock['realm'], 0, 29), 30);
					
					// Nombre de clés
					$length = sizeof($lock['keys']);
					
					// Curseur
					echo str_pad("" . $lock['cursor'], 8);
					
					// Left
					echo str_pad("" . ($length - $lock['cursor']), 8);
					
					// Timestamp
					echo $lock['lasttime'];
					
					// Eol
					echo PHP_EOL;
					
					// On libére la mémoire
					unset($lock);
					
				}
				return true;
			
			// Command not found
			default :
				echo "$action: action not found" . PHP_EOL;
				echo "Type '$cmd --help' to display available commands." . PHP_EOL;
				return false;
			
		}
		
		// In case of...
		return false;
		
	}
	
	/**
	 * Lets change the flags field for a user.
	 *
	 * @requireFlags ua
	 * @cmdPackage Security
	 */
	function handle_chflag($file, $cmd, $params, $argv) {
		
		// Aide
		if (isset($params['help'])) {
			echo "Usage: chflag FLAGS USER [USERS..]" . PHP_EOL;
			echo "The valid flags under FLAGS are:" . PHP_EOL;
			foreach (WG::flags() as $flag) {
				echo " " . $this->bold($flag['flag']) . "  " . $flag['description'] . PHP_EOL;
			}
			return true;
		}
		
		// Check arguments
		if (sizeof($argv) < 2 || !isset($argv[1])) {
			echo "Usage: chflag [--help] FLAGS USER [USERS..]" . PHP_EOL;
			return false;
		}
		
		// Check flags entry
		$flags = $argv[0];
		unset($argv[0]);
		if (preg_match('/^[a-zA-Z\+\-]{1,}$/', $flags) !== 1) {
			echo "Error: invalid flag modifier `$flags`" . PHP_EOL;
			return false;
		}
		
		// On va avoir besoin de ça
		$level = WG::user()->level();
		
		// On parse le paramètre flags pour trier en trois catégories :
		// - set : les flags à mettre
		// - add : les flags à rajouter aux flags existants
		// - del : les flags à supprimer
		$set = $add = $del = array();
		$mod = '';
		for ($i = 0, $l = strlen($flags); $i < $l; $i++) {
			$c = $flags{$i};
			if ($c == '+') $mod = '+';
			else if ($c == '-') $mod = '-';
			else {
				// On recupère le niveau du flag
				$lvl = TeamMember::flaglevel($c);
				// On vérifie que le flag existe bien dans la config
				if ($lvl < 0) {
					echo "Error: unknown flag `$c`" . PHP_EOL;
					return false;
				}
				// Verification de level : le flag à appliquer ne doit pas d'être d'un niveau
				// > ou = à celui de l'utilisateur courant.
				if ($lvl >= $level) {
					echo "Error: you are not allowed to apply flag `$c`" . PHP_EOL;
					return false;
				}
				if ($mod == '+') $add[] = $c;
				else if ($mod == '-') $del[] = $c;
				else $set[] = $c;
			}
		}
		
		// On recupère le model des users accounts
		$model = ModelManager::get('TeamMember');
		
		// On parcours les noms d'utilisateurs
		foreach ($argv as $k => $username) {
			
			// On ne prends pas en compte les modifiers
			if (!is_int($k)) continue;
			
			// Pour l'utilisateur système c'est même pas la peine d'essayer
			if ($username === WG::vars('mimo_account_username')) {
				echo "Error: can't modifiy system user." . PHP_EOL;
				return false;
			}
			
			// On recupère l'utilisateur
			$user = $model->getByLogin($username, 1);
			
			// User not found
			if (!$user) {
				echo "Error: user `$username` not found" . PHP_EOL;
				return false;
			}
			
			// Verification de level : le niveau de l'utilisateur à modifier ne doit pas être
			// > ou = à celui de l'utilisateur courant.
			if ($user->level() >= $level) {
				echo "Error: you are not allowed to change `$username` flags" . PHP_EOL;
				return false;
			}
			
			// On recupère les flags de l'utilisateur
			$cflags = $user->flags;
			
			// Set
			if (sizeof($set) > 0) {
				$cflags = implode('', $set);
			}
			// Add
			foreach ($add as $a) {
				if (strpos($cflags, $a) === false) $cflags .= $a;
			}
			// Delete
			foreach ($del as $a) {
				$cflags = str_replace($a, '', $cflags);
			}
			
			echo "Apply `$flags` to user `$username` ({$user->flags} -> $cflags) ..." . PHP_EOL;
			
			if ($cflags != $user->flags) {
				$user->set('flags', $cflags)->save();
			}
		}
		
		return true;

	}


	/**
	 * @cmdAlias flaglist
	 */
	function handle_flags($file, $cmd, $params, $argv) {
		return $this->handle_flaglist($file, $cmd, $params, $argv);
	}

	/**
	 * List all users flags available on the system.
	 *
	 * @requireFlags ua
	 * @allowedParams
	 * @cmdPackage Security
	 */
	function handle_flaglist($file, $cmd, $params, $argv) {
		if (!$this->check()) {
			return false;
		}
		$flags = array();
		foreach (WG::flags() as $flag) {
			$level = $flag['level'];
			if (!isset($flags[$level])) {
				$flags[$level] = array();
			}
			$flag['level'] = $level;
			$flags[$level][] = $flag;
		}
		ksort($flags, SORT_NUMERIC);
		$flags = array_reverse($flags);
		echo "LEVEL FLAG NAME            MODULE               DESCRIPTION" . PHP_EOL;
		echo "---------------------------------------------------------------" . PHP_EOL;
		$c = 0;
		foreach ($flags as $level => $cat) {
			foreach ($cat as $flag) {
				echo str_pad(substr($flag['level'], 0, 4), 4, ' ', STR_PAD_LEFT) . '   ';
				echo str_pad(substr($flag['flag'], 0, 4), 4);
				echo str_pad(substr($flag['name'], 0, 15), 16);
				echo str_pad(substr($flag['module'], 0, 20), 21);
				echo trim(chunk_split($flag['description'], 70, PHP_EOL . '                                                '));
				echo PHP_EOL;
				$c++;
			}
		}
		echo "Total: $c" . PHP_EOL;
		return true;
	}

	/**
	 * Execute a command as another user.
	 *
	 * @requireFlags u
	 * @allowedParams d help user passwd passwdcr
	 * @cmdPackage Users & Groups
	 * @cmdUsage sudo [USER]
	 * @cmdUsage sudo [-d]
	 */
	function handle_sudo($file, $cmd, $params, $argv, &$context) {
		
		// Verification des arguments/flags
		if (!$this->check()) {
			return false;
		}
		
		// Aide
		if (isset($params['help'])) {
			echo "Usage: sudo [USER]" . PHP_EOL;
			echo "Usage: sudo [-d]" . PHP_EOL;
			return true;
		}
		
		// Unsudo : on le fait toujours sans discuter
		if (isset($params['d'])) {
			WGCRT_Session::startedSession()->unsudo();
			echo "OK" . PHP_EOL;
			return true;
		}
		
		// Par défaut, le sudo s'applique à l'user root
		$username = isset($params[0]) ? $params[0] : "{$this->defaultSudoUser}";
		
		// Pour l'utilisateur systeme ce n'est pas possible
		if ($username == WG::vars('mimo_account_username')) {
			echo "Can't use sudo on the system user." . PHP_EOL;
			return true;
		}
		
		// On cherche l'utilisateur
		$user = ModelManager::get('TeamMember')->getByLogin($username, 1);
		
		// On a besoin de la session courante
		$session = WGCRT_Session::startedSession();
		
		// On regarde s'il s'agit déjà de l'utilisateur utilisé
		if ($session->getUser()->get('login') == $username) {
			// Dans ce cas, on évite l'opération mais on retourne un code positif
			echo "But, you " . $this->bold("are") . " $username..." . PHP_EOL;
			return true;
		}
		
		// On regarde maintenant s'il s'agit de l'utilisateur originel
		if ($session->getRealUser()->get('login') == $username) {
			// Dans ce cas on fait un unsudo et on renvoi true
			$session->unsudo();
			echo "OK" . PHP_EOL;
			return true;
		}
		
		// User not found
		if (!$user) {
			echo "User not found: " . $this->bold($username) . PHP_EOL;
			return false;
		}
		
		// On regarde si le mot de passe a été renseigné (en clair)
		if (array_key_exists('passwd', $params)) {
			
			// On recupère le mdp
			$params['passwd'] = trim($params['passwd']);
				
			// Si le mot de passe est vide, on assimile ça à un cancel
			if (empty($params['passwd'])) {
				return false;
			}
			
			if (!$this->allowSudoWithClearPassword) {
				echo "Sudo with clear password is disabled." . PHP_EOL;
				return false;
			}
			
			if ($user->get('password') === sha1($username . ':' . $params['passwd'])) {
				WGCRT_Session::startedSession()->sudo($user);
				echo "OK" . PHP_EOL;
				return true;
			}
			else {
				echo "Invalid password" . PHP_EOL;
				return false;
			}
		}
		
		// On regarde si le mot de passe a été renseigné (en crypté)
		else if (array_key_exists('passwdcr', $params)) {

			if (sha1(session_id() . ':' . $user->get('password')) === $params['passwdcr']) {
				WGCRT_Session::startedSession()->sudo($user);
				echo "OK" . PHP_EOL;
				return true;
			}
			else {
				echo "Invalid password" . PHP_EOL;
				return false;
			}
			
			return true;
		}
		
		// Sinon on demande le mot de passe
		else {
			echo "Enter password for: " . $user->get('login') . PHP_EOL;
			// Si le shell supporte les mots de passes en cryptés
			if ($this->supportHashedPassword) {
				// On inscrit le nom d'utilisateur cible dans le contexte
				$context['concat'] = $user->get('login');
				// Et on renvoi le code pour indiquer au client ce qu'il a à faire
				return self::INPUT_PWD_CONCAT_USER_SHA1;
			}
			else {
				return self::INPUT_PWD;
			}
		}
	}

	/**
	 * Get informations about a particular user.
	 *
	 * @requireFlags u
	 * @allowedParams help
	 * @cmdPackage Users & Groups
	 * @cmdUsage whois
	 * @cmdUsage whois [ USER [ USERS.. ] ]
	 */
	function handle_whois($file, $cmd, $params, $argv) {
		if (!$this->check()) {
			return false;
		}
		if (isset($params['help'])) {
			echo "Usage: whois [ USER [ USERS.. ] ]" . PHP_EOL;
			return true;
		}
		if (sizeof($params) < 1 || !isset($params[0])) {
			$session = WGCRT_Session::startedSession();
			if ($session && $session->isLogged()) {
				$user = $session->getUser();
				if ($session->isSudo()) {
					echo 'You are ' . $session->getRealUser()->get('login');
					echo ', logged as ' . $this->orange($this->bold($user->get('login')));
				}
				else {
					echo 'You are ' . $this->orange($this->bold($session->getRealUser()->get('login')));
				}
				echo ' (level ' . $user->level() . ', flags ' . $user->get('flags') . ', UID #' . $user->get('id') . ')' . PHP_EOL;
			}
			else {
				echo "Your are not connected." . PHP_EOL;
			}
			return true;
		}
		foreach ($params as $k => $username) {
			if (!is_int($k)) continue;
			$user = ModelManager::get('TeamMember')->getByLogin($username, 1);
			$sysuser = WG::vars('mimo_account_username');
			if (!$user) {
				echo "Error: user `$username` not found" . PHP_EOL;
				continue;
			}
			$grp = array();
			$groups = ModelManager::get('UserGroup')->getByUser($user->id);
			if (sizeof($groups) > 0) {
				foreach ($groups as $group) $grp[] = $group->group;
			}
			echo "  _____" . PHP_EOL;
			echo " |  o  | " . $this->bold($user->name) . ($username == $sysuser ? ' [System User]' : '') . PHP_EOL;
			echo " | /|\\ | <{$user->email}>" . PHP_EOL;
			echo " | / \ | Flags: {$user->flags} (" . $user->levelname() . ")" . PHP_EOL;
			echo " |_____| Groups: " . implode(', ', $grp) . PHP_EOL;
		}
		return true;
	}
	
	/**
	 * Auto-completion pour la commande 'digest'
	 */
	function handle_digest_autocomplete($args, &$r) {
		// 1er argument: nom de fichier digest
		if (sizeof($args) === 2) {
			$digests = array();
			foreach (WG::htdigests() as $info) {
				// Verification des flags
				if (isset($info['requireFlags'])) {
					if (!WG::checkFlags($info['requireFlags'])) continue;
				}
				// Verification des groupes
				if (isset($info['requireGroup'])) {
					if (!WG::checkGroups($info['requireGroup'])) continue;
				}
				// On ajoute le digest dans la liste
				$digests[] = $info['realm'];
			}
			$this->autocompleteFilter($args[1], $digests, $r);
		}
	}

	/**
	 * List htdigest files.
	 *
	 * @requireFlags ua
	 * @allowedParams help
	 * @cmdPackage Security
	 */
	function handle_digest($file, $cmd, $params, $argv) {
		if (!$this->check()) {
			return false;
		}
		if (isset($params['help'])) {
			echo "Usage: digest [REALM]" . PHP_EOL;
			return true;
		}
		// Detail
		if (isset($params[0])) {
	
			// Recupération des données du digest
			$info = WG::htdigest($params[0]);
	
			// Not found
			if ($info === null) {
				echo "Error: realm `{$params[0]}` not found" . PHP_EOL;
				return false;
			}
	
			// Verification des flags
			if (isset($info['requireFlags'])) {
					if (!WG::checkFlags($info['requireFlags'])) {
						echo "Error: you are not allowed to display this realm" . PHP_EOL;
						return false;
					}
			}
			// Verification des groupes
			if (isset($info['requireGroup'])) {
				if (!WG::checkGroups($info['requireGroup'])) {
					echo "Error: you are not allowed to display this realm" . PHP_EOL;
					return false;
				}
			}
	
			$c = 0;
			echo "USER                 REALM                HASH" . PHP_EOL;
			echo "--------------------------------------------------" . PHP_EOL;
			foreach (WG::htdigest($params[0], true) as $entry) {
				echo str_pad(substr($entry['user'], 0, 20), 21);
				echo str_pad(substr($entry['realm'], 0, 20), 21);
				echo $entry['hash'];
				echo PHP_EOL;
				$c++;
			}
			echo "Total: $c" . PHP_EOL;
			return true;
		}
	
		// List
		else {
			echo "MODULE               REALM                USERS FILE" . PHP_EOL;
			echo "--------------------------------------------------------" . PHP_EOL;
			$c = 0;
			foreach (WG::htdigests() as $info) {
		
				// Verification des flags
				if (isset($info['requireFlags'])) {
					if (!WG::checkFlags($info['requireFlags'])) continue;
				}
			
				// Verification des groupes
				if (isset($info['requireGroup'])) {
					if (!WG::checkGroups($info['requireGroup'])) continue;
				}
			
				echo str_pad(substr($info['module'], 0, 20), 21);
				echo str_pad(substr($info['realm'], 0, 20), 21);
				$i = 0;
				$fg = @file_get_contents(WG::base($info['file']));
				if ($fg) {
					foreach (explode("\n", $fg) as $entry) {
						$entry = trim($entry);
						if (empty($entry) || $entry{0} == '#') continue;
						$i++;
					}
				}
				echo str_pad(substr($i === 0 ? '0' : $i, 0, 5), 6);
				echo '/' . $info['file'];
				echo PHP_EOL;
				$c++;
			
			}
			echo "Total: $c" . PHP_EOL;
			return true;
		}
	
	}
	
	/**
	 * Fonction de base pour l'auto-completion des groupes.
	 */
	function autocomplete_groups($arg, &$r) {
		$groups = array();
		$db = WG::database();
		$query = $db->query("SELECT `group` FROM `".$db->getDatabaseName()."`.`".$db->getPrefix()."usergroup` WHERE 1 GROUP BY `group`");
		foreach ($query as $row) {
			$groups[] = $row->get('group');
		}
		$this->autocompleteFilter($arg, $groups, $r);
	}

}

?>