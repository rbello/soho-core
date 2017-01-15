<?php

class Soho_CLI_Packages extends Soho_CLI_Files {

	/**
	 * @cmdAlias packages
	 */
	function handle_p($file, $cmd, $params, $argv) {
		return $this->handle_packages($file, $cmd, $params, $argv);
	}

	/**
	 * Handle sofware packages.
	 *
	 * @requireFlags c
	 * @allowedParams help purge update m t
	 * @cmdPackage Developper
	 */
	function handle_packages($file, $cmd, $params, $argv) {
		
		if (!$this->check()) {
			return false;
		}
		
		if (isset($params['help'])) {
			echo "Usage: $cmd                                                      List all packages." . PHP_EOL;
			echo "Usage: $cmd [[REPOSITORY/]PACKAGE] [PROPERTY]                    Get info about a package." . PHP_EOL;
			echo "Usage: $cmd --update [REPOSITORY/]PACKAGE -m MESSAGE [-t TAGS]   Update revision control." . PHP_EOL;
			echo "Usage: $cmd --purge [[REPOSITORY/]PACKAGE]                       Purge package cache." . PHP_EOL;
			return true;
		}
		
		// API de gestion des paquets logiciels
		$api = WG::softwarepackages();
		
		// Purge
		if (isset($params['purge'])) {
			
			// Le paramètre a été spécifié seul, c'est donc une purge globale
			if ($params['purge'] === true) {
				$api->purgePersistentData();
				return true;
			}
			
			// Sinon il s'agit de la purge d'un package en particulier
			$package = $api->searchPackages($params['purge']);
			
			// Traitement du résultat
			if (sizeof($package) < 1) {
				echo "Package not found: {$params['purge']}" . PHP_EOL;
				return false;
			}
			else if (sizeof($package) > 1) {
				// Plusieurs packages portent ce nom, il faut demander au client de
				// préciser en ajoutant le namespace.
				echo "Package name `{$params['purge']}` is ambiguous. Please specify a repository name." . PHP_EOL;
				foreach ($package as $p) {
					echo "  {$p['package.repository']}/{$p['package.namespace']}.{$p['package.name']}" . PHP_EOL;
				}
				return false;
			}
			else {
				$package = $package[0];
			}
			
			// On purge
			$api->purgePersistentState(
				$p['package.repository'],
				$p['package.namespace'] . '.' . $p['package.name']
			);
			
			return true;
			
		}
		
		// Update
		if (isset($params['update'])) {
			
			// Le paramètre ne peut pas être appelé seul
			if ($params['update'] === true) {
				echo "Usage: $cmd -m MESSAGE --update [REPOSITORY/]PACKAGE" . PHP_EOL;
				return false;
			}
				
			// On recherche le package
			$package = $api->searchPackages($params['update']);
				
			// Traitement du résultat
			if (sizeof($package) < 1) {
				echo "Package not found: {$params['update']}" . PHP_EOL;
				return false;
			}
			else if (sizeof($package) > 1) {
				// Plusieurs packages portent ce nom, il faut demander au client de
				// préciser en ajoutant le namespace.
				echo "Package name `{$params['update']}` is ambiguous. Please specify a repository name." . PHP_EOL;
				foreach ($package as $p) {
					echo "  {$p['package.repository']}/{$p['package.namespace']}.{$p['package.name']}" . PHP_EOL;
				}
				return false;
			}
			else {
				$package = $package[0];
			}
			
			// On force l'entrée d'un message
			if (!isset($params['m']) || !is_string($params['m'])) {
				echo "You have to fill in a message." . PHP_EOL;
				echo "Usage: $cmd -m MESSAGE --update {$params['update']}" . PHP_EOL;
				return false;
			}
			
			// On détermine le message et les tags à associer à la révision
			$message = '' . $params['m'];
			$tags = isset($params['t']) ? explode(',', '' . $params['t']) : array();
			
			// On demande la mise à jour du dossier du projet. Il sera parcouru récursivement.
			// Au passage, on recupère la liste des noeuds mis à jour
			$updated = WG::commit()->updatePackageSources(
				$package['package.repository'], // Nom du repository
				$package['package.namespace'] . '.' . $package['package.name'], // Nom du package
				$package['package.folder'], // Chemin vers le répertoire des sources
				$message, // Message associé au commit
				$tags // Tableau des tags à associer au commit
			);
			
			// Si des fichiers ont été mis à jour
			if (sizeof($updated) > 0) {
				// On affiche la liste des fichiers mis à jour
				echo 'Updated: ' . implode(PHP_EOL . 'Updated: ', array_keys($updated)) . PHP_EOL;
			}
				
			return true;
			
		}
		
		// Détail d'un package
		if (isset($params[0])) {

			// Recherche du package
			$package = $api->searchPackages($params[0]);

			// Traitement du résultat
			if (sizeof($package) < 1) {
				echo "Package not found: {$params[0]}" . PHP_EOL;
				return false;
			}
			else if (sizeof($package) > 1) {
				// Plusieurs packages portent ce nom, il faut demander au client de
				// préciser en ajoutant le namespace.
				echo "Package name `{$params[0]}` is ambiguous. Please specify a repository name." . PHP_EOL;
				foreach ($package as $p) {
					echo "  {$p['package.repository']}/{$p['package.namespace']}.{$p['package.name']}" . PHP_EOL;
				}
				return false;
			}
			else {
				$package = $package[0];
			}
			
			// Nom complet du package
			$name = $package['package.namespace'] . '.' . $package['package.name'];
			
			// On recupère l'état actuel du package
			$package = $api->getPackageState(
				$package['package.repository'],
				$name
			);
			
			// Erreur
			if (!is_object($package)) {
				echo "Unable to get package state: $name" . PHP_EOL;
				return false;
			}

			
			// Project name
			if (isset($package['package.projectname'])) {
				echo "Project name  : " . $this->bold($this->orange($package['package.projectname'])) . PHP_EOL;
			}
			
			// Package informations
			echo "Package       : " . $this->bold($name) . PHP_EOL;
			echo "Repository    : {$package['package.repository']}" . PHP_EOL;
			echo "Directory     : {$package['package.folder']}" . PHP_EOL;
			
			// Build targets
			if (isset($package['build.targets'])) {
				echo "Build targets : " . implode(', ', array_keys($package['build.targets'])) . PHP_EOL;
			}
			
			// Last modification
			if (isset($package['files.lastmtime'])) {
				echo "Last update   : " . WG::rdate($package['files.lastmtime']) . PHP_EOL;
			}
			
			// Project size
			if (isset($package['files.totalsize'])) {
				echo "Total size    : " . format_bytes($package['files.totalsize']) . PHP_EOL;
			}
			
			// Project version
			if (isset($package['build.properties']['project.version'])) {
				echo "Version       : " . $this->green($package['build.properties']['project.version']) . PHP_EOL;
			}
			
			// Custom attribute
			if (isset($params[1])) {
				
				if (isset($package['build.properties']['project.' . $params[1]])) {
					echo $params[1] . ': ';
					print_r($package['build.properties']['project.' . $params[1]]);
					echo PHP_EOL;
				}
				else {
					echo "$cmd: property '{$params[1]}' not found" . PHP_EOL;
					return false;
				}
				
			}

			return true;
			
		}
		
		echo "REPOSITORY   NAMESPACE    PACKAGE                        DIRECTORY" . PHP_EOL;
		echo "----------------------------------------------------------------------" . PHP_EOL;
		$c = 0;
		foreach ($api->getPackagesList() as $package) {
			echo str_pad(substr($package['package.repository'], 0, 12), 13);
			echo str_pad(substr($package['package.namespace'], 0, 12), 13);
			echo str_pad(substr($package['package.name'], 0, 30), 31);
			echo $package['package.folder'];
			echo PHP_EOL;
			$c++;
		}
		echo "Total: $c" . PHP_EOL;
		return true;
	}

	/**
	 * Auto-completion pour la commande 'rev'
	 */
	function handle_rev_autocomplete($args, &$r) {
		$this->handle_revisioncontrol_autocomplete($args, $r);
	}

	/**
	 * @cmdAlias versioncontrol
	 */
	function handle_rev($file, $cmd, $params, $argv) {
		return $this->handle_versioncontrol($file, $cmd, $params, $argv);
	}

	/**
	 * Auto-completion pour la commande 'revisioncontrol'
	 */
	function handle_revisioncontrol_autocomplete($args, &$r) {
		// 1er argument = sous-commandes
		if (sizeof($args) === 2) {
			$this->autocompleteFilter($args[1], array('update', 'info', 'list', 'diff', 'revert'), $r);
		}
		// A partir du 2ème argument = chemin vers un fichier
		else if (sizeof($args) > 2) {
			$this->autocomplete_files($args, $r);
		}
	}

	/**
	 * Manage revision data.
	 *
	 * @requireFlags c
	 * @allowedParams help m t r
	 * @cmdPackage Developper
	 */
	function handle_versioncontrol($file, $cmd, $params, $argv) {

		// On vérifie droits et arguments
		if (!$this->check()) {
			return false;
		}

		// Pas assez d'arguments
		if (!isset($params[0]) || isset($params['help'])) {
			echo "Available sub-commands are:" . PHP_EOL;
			echo " $cmd update [-m MESSAGE] [-t TAGS] [PATH]   Update revisions with current files." . PHP_EOL;
			echo " $cmd info PATH                              Display info about a file and revisions it refers to." . PHP_EOL;
			echo " $cmd list PATH                              List all revisions of a file." . PHP_EOL;
			echo " $cmd diff [ -r RV1:RV2 ] PATH               Print a text-diff between revisions." . PHP_EOL;
			echo " $cmd revert [ -r RV ] PATH                  Undo changes in current file and restore a revision." . PHP_EOL;
			echo "Tags are separacted by coma." . PHP_EOL;
			return false;
		}

		// Recupèrer la commande
		$com = $params[0];
		unset($params[0]);

		switch (strtolower($com)) {
				
			case 'info'		: return $this->revisioncontrol_info($file, $cmd, $params, $argv); break;
			case 'update'	: return $this->revisioncontrol_update($file, $cmd, $params, $argv); break;
			case 'list'		: return $this->revisioncontrol_list($file, $cmd, $params, $argv); break;
			case 'diff'		: return $this->revisioncontrol_diff($file, $cmd, $params, $argv); break;

			default :
				echo "$cmd: invalid option `$com`" . PHP_EOL;
				return false;
					
		}

	}

	/**
	 * Afficher les informations de révisions sur un noeud
	 */
	protected function revisioncontrol_info($file, $cmd, $params, $argv) {

		if (!isset($params[1])) {
			echo "Usage: $cmd info PATH" . PHP_EOL;
			return false;
		}

		$node = $params[1];

		// Par rapport à l'emplacement actuel
		if (substr($node, 0, 1) !== '/' && isset($_SESSION['cd'])) {
			$node = $_SESSION['cd'] . $node;
		}

		try {

			// On recupère les API qui vont nous servir
			$files = WG::files();
			$versioncontrol = WG::versioncontrol();

			// On vérifie que $node soit un fichier valide
			// En cas d'erreur, une exception WGFilesIOException sera levée
			// Au passage, $node est nettoyé
			$node = $files->checkNode($node, false, true);

			// On vérifie que l'utilisateur puisse lire la ressource
			// En cas d'erreur, une exception WGFilesNeedPrivilegesException sera levée
			$files->checkCurrentUserPrivileges($node, 'read', true);

			// On recupère des infos sur le chemin
			$info = $files->pathinfo($node, PATHINFO_TIMES);

			// On recupère l'identifiant du noeud
			$uid = $files->getNodeUID($node);

			// On recupère les révisions de ce fichier
			$revisions = $versioncontrol->getFileRevisions($uid);

			// On comptabilise le nombre de révisions
			$count = sizeof($revisions);

			// On affiche les informations "globales"
			echo "Node UID         : " . $this->bold($uid) . PHP_EOL;
			echo "Path             : " . $this->bold($node) . PHP_EOL;
			echo "Size             : " . $this->bold(format_bytes($info['size'])) . PHP_EOL;
			echo "Revisions        : " . $this->bold($count === 0 ? '0' : $count) . PHP_EOL;

			// Affichage de la dernière révision
			if ($count > 0) {

				// On recupère la dernière révision
				$lastRev = array_pop($revisions);

				echo "---------" . PHP_EOL;
				echo "Last revision    : " . $this->bold($lastRev['revc']) . PHP_EOL;
				echo "Date             : " . $this->bold(WG::rdate($lastRev['date'])) . PHP_EOL;
				echo "Size             : " . $this->bold(format_bytes($lastRev['size'])) . PHP_EOL;
				echo "User             : " . $this->bold($lastRev['user']) . PHP_EOL;
				if (!empty($lastRev['tags'])) {
					echo "Tags             : " . $this->bold(implode(', ', $lastRev['tags'])) . PHP_EOL;
				}
				if (!empty($lastRev['msg'])) {
					echo "Message          : " . $lastRev['msg'] . PHP_EOL;
				}

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
	 * Mettre à jour les fichiers du répertoire actuel ou spécifié par rapport
	 * aux dernières révisions de versioncontrol.
	 */
	protected function revisioncontrol_update($file, $cmd, $params, $argv) {

		// Le chemin est spécifié
		if (isset($params[1])) {
			$node = $params[1];
			// Par rapport à l'emplacement actuel
			if (substr($node, 0, 1) !== '/' && isset($_SESSION['cd'])) {
				$node = $_SESSION['cd'] . $node;
			}
		}

		// Le chemin n'est pas spécifié : on prends le CD
		else {
			$node = isset($_SESSION['cd']) ? $_SESSION['cd'] : '/';
		}

		try {

			// On a besoin de ces APIs
			$files = WG::files();
			$versioncontrol = WG::versioncontrol();

			// On vérifie que l'utilisateur puisse utiliser la ressource
			// En cas d'erreur, une exception WGFilesNeedPrivilegesException sera levée
			$files->checkCurrentUserPrivileges($node, 'bind', true);

			// On détermine le message et les tags à associer à la révision
			$message = isset($params['m']) ? '' . $params['m'] : '';
			$tags = isset($params['t']) ? explode(',', '' . $params['t']) : array();
			
			// On demande la mise à jour du noeud. Si c'est un dossier,
			// il sera parcouru récursivement.
			// Au passage, on recupère la liste des noeuds mis à jour
			$updated = $versioncontrol->updateNode(
				$node, // Chemin vers le noeud
				$message, // Message
				$tags, // Tags
				true // Recursive
			);

			// Si des fichiers ont été mis à jour
			if (sizeof($updated) > 0) {
			
				// On affiche la liste des fichiers mis à jour
				echo 'Updated: ' . implode(PHP_EOL . 'Updated: ', array_keys($updated)) . PHP_EOL;
				
				// On ajoute un commit
				// Non, pour l'instant on ne fait des commit que sur les projets (commande 'packages')
				//$versioncontrol->addCommit($message, $tags, $updated);

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
	 * Afficher la liste des révisions d'un fichier
	 */
	protected function revisioncontrol_list($file, $cmd, $params, $argv) {

		if (!isset($params[1])) {
			echo "Usage: $cmd list PATH" . PHP_EOL;
			return false;
		}

		$node = $params[1];

		// Par rapport à l'emplacement actuel
		if (substr($node, 0, 1) !== '/' && isset($_SESSION['cd'])) {
			$node = $_SESSION['cd'] . $node;
		}

		try {

			// On recupère les API qui vont nous servir
			$files = WG::files();
			$versioncontrol = WG::versioncontrol();

			// On vérifie que $node soit un fichier valide
			// En cas d'erreur, une exception WGFilesIOException sera levée
			// Au passage, $node est nettoyé
			$node = $files->checkNode($node, false, true);

			// On vérifie que l'utilisateur puisse lire la ressource
			// En cas d'erreur, une exception WGFilesNeedPrivilegesException sera levée
			$files->checkCurrentUserPrivileges($node, 'read', true);

			// On recupère l'identifiant du noeud
			$uid = $files->getNodeUID($node);

			// On recupère les révisions de ce fichier
			$revisions = $versioncontrol->getFileRevisions($uid);
				
			// On parcours les révisions
			foreach ($revisions as $rev) {

				echo "Revision : " . $this->bold($this->orange($rev['revc'])) . PHP_EOL;
				echo "Date     : " . WG::rdate($rev['date']) . PHP_EOL;
				echo "Size     : " . format_bytes($rev['size']) . PHP_EOL;
				echo "User     : " . $rev['user'] . PHP_EOL;
				if (!empty($rev['tags'])) {
					echo "Tags     : " . $this->bold($this->green(implode(', ', $rev['tags']))) . PHP_EOL;
				}
				if (!empty($rev['msg'])) {
					echo "Message  : " . $rev['msg'] . PHP_EOL;
				}
				echo "----------" . PHP_EOL;

			}
				
			echo "Total: " . sizeof($revisions) . PHP_EOL;

			return true;

		}
		// En cas d'erreur, on traite les exceptions
		catch (Exception $ex) {
			echo "$cmd: " . $ex->getMessage() . PHP_EOL;
			return false;
		}

	}

	/**
	 * Afficher le differentiel entre deux versions
	 */
	protected function revisioncontrol_diff($file, $cmd, $params, $argv) {

		if (!isset($params[1])) {
			echo "Usage: $cmd diff [ -r RV1:RV2 ] PATH" . PHP_EOL;
			return false;
		}

		$node = $params[1];

		// Par rapport à l'emplacement actuel
		if (substr($node, 0, 1) !== '/' && isset($_SESSION['cd'])) {
			$node = $_SESSION['cd'] . $node;
		}

		try {

			// On recupère les API qui vont nous servir
			$files = WG::files();
			$versioncontrol = WG::versioncontrol();

			// On vérifie que $node soit un fichier valide
			// En cas d'erreur, une exception WGFilesIOException sera levée
			// Au passage, $node est nettoyé
			$node = $files->checkNode($node, false, true);

			// On vérifie que l'utilisateur puisse lire la ressource
			// En cas d'erreur, une exception WGFilesNeedPrivilegesException sera levée
			$files->checkCurrentUserPrivileges($node, 'read', true);

			// On recupère l'identifiant du noeud
			$uid = $files->getNodeUID($node);

			// On recupère les révisions de ce fichier
			$revisions = $versioncontrol->getFileRevisions($uid);
				
			// Aucune révision : le fichier n'est pas synchronisé
			if (sizeof($revisions) < 1) {
				echo "This file has no revision." . PHP_EOL;
				return false;
			}

			// Des révisions sont renseignées
			if (isset($params['r'])) {

				$rev = explode(':', $params['r'], 2);

				// Les deux versions sont indiquées
				if (sizeof($rev) > 1) {

					// Conversion en entier
					$rev[0] = intval($rev[0]);
					$rev[1] = intval($rev[1]);
						
					// On vérifie que les révisions existent pour ce fichier
					if (!array_key_exists($rev[0], $revisions)) {
						echo "This file has no revision: {$rev[0]}" . PHP_EOL;
						return false;
					}
					if (!array_key_exists($rev[1], $revisions)) {
						echo "This file has no revision: {$rev[1]}" . PHP_EOL;
						return false;
					}
						
					// Version A : la première révision
					$nameA = 'rev ' . $rev[0];
					$fileA = $versioncontrol->realpath($rev[0]);
						
					// Version B : la seconde révision
					$nameB = 'rev ' . $rev[1];
					$fileB = $versioncontrol->realpath($rev[1]);
						
				}

				// Uniquement la première version est indiquée
				// On compare cette version avec le fichier actuel
				else {

					// Conversion en entier
					$rev[0] = intval($rev[0]);

					// On vérifie que les révisions existent pour ce fichier
					if (!array_key_exists($rev[0], $revisions)) {
						echo "This file has no revision: {$rev[0]}" . PHP_EOL;
						return false;
					}

					// Version A : la révision
					$nameA = 'rev ' . $rev[0];
					$fileA = $versioncontrol->realpath($rev[0]);

					// Version B : la version actuelle
					$nameB = 'current';
					$fileB = $files->realpath($node);

				}

			}
				
			// Sinon on compare la version actuelle à la dernière révisions.
			else {

				// On recupère la dernière version
				$lastRev = array_pop($revisions);

				// Version A : la révision
				$nameA = 'last-rev ('.$lastRev['revc'].')';
				$fileA = $versioncontrol->realpath($lastRev['revc']);

				// Version B : la version actuelle
				$nameB = 'current';
				$fileB = $files->realpath($node);

			}
				
			// On tente la récupération du contenu des fichiers
			if (($contentA = file_get_contents($fileA)) === false) {
				echo "Unable to read: $nameB version" . PHP_EOL;
				return false;
			}
			if (($contentB = file_get_contents($fileB)) === false) {
				echo "Unable to read: $nameB version" . PHP_EOL;
				return false;
			}
				
			$contentA = file_get_contents($fileA);
			$contentB = file_get_contents($fileB);
				
			WG::lib('Pear/Text/Diff.php');

			$diff = $versioncontrol->getStringDiff($contentA, $contentB);

			if (!is_array($diff)) {
				echo "$cmd: unable to make diff" . PHP_EOL;
				return false;
			}
				
			echo $this->bold("$nameA VS $nameB") . PHP_EOL;
			//echo $this->bold("$fileA VS $fileB") . PHP_EOL;
			//echo $this->red($contentA) . PHP_EOL . $this->green($contentB) . PHP_EOL;
				
			$c = true;
			$r = array();
			$l1 = 1;
			$l2 = 1;
			$r = array();
			foreach ($diff as $d) {
				$s1 = '0';
				$s2 = '0';
				switch (get_class($d)) {
						
					case 'Text_Diff_Op_copy' :
						$s1 = $s2 = sizeof($d->final);
						$l1 += $s1;
						$l2 += $s2;
						$out = "\n " . implode("\n ", $d->final);
						break;

					case 'Text_Diff_Op_add' :
						$c = false;
						$out = $this->green("\n+" . implode("\n+", $d->final));
						$s2 = sizeof($d->final);
						$l2 += $s2;
						break;

					case 'Text_Diff_Op_change' :
						$c = false;
						$out = $this->orange("\n-" . implode("\n-", $d->orig));
						$s1 = sizeof($d->orig);
						$l1 += $s1;
						$out .= $this->orange($this->bold("\n+" . implode("\n+", $d->final)));
						$s2 = sizeof($d->final);
						$l2 += $s2;
						break;

					case 'Text_Diff_Op_delete' :
						$c = false;
						$out = $this->red("\n-" . implode("\n-", $d->orig));
						$s1 = sizeof($d->orig);
						$l1 += $s1;

				}
				if (!$this->styles) {
					$r[] = "\n@@ -$l1,$s1 +$l2,$s2 @@$out";
				}
				else {
					$r[] = $out;
				}
			}
				
			if ($c) {
				echo "No change" . PHP_EOL;
			}
			else {
				echo implode('', $r) . PHP_EOL;
			}

			return true;

		}
		// En cas d'erreur, on traite les exceptions
		catch (Exception $ex) {
			echo "$cmd: " . $ex->getMessage() . PHP_EOL;
			return false;
		}

	}



}

?>