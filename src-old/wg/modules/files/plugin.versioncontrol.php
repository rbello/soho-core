<?php

/**
 * 
 * Gestion du versionning pour le service de fichier
 * 
 */
class Soho_Plugin_Files_VersionControl extends Soho_PluginBase {
	
	/**
	 * Lien vers l'API de gestion de fichier.
	 * @var Soho_Plugin_Files
	 */
	protected $files = null;
	
	/**
	 * Chemin vers le dossier contenant les fichiers de révision.
	 * @var string
	 */
	protected $revcDir;
	
	/**
	 * Compteur de fichier de revisions.
	 * @var int
	 */
	protected $revCount = 1;
	
	/**
	 * Cache des révisions.
	 * @var mixed[][]
	 */
	protected $revisions = array();
	
	/**
	 * Constructeur de la classe.
	 */
	public function __construct() {

		// On renseigne le nom du plugin
		parent::__construct('versioncontrol');
	
		// On rends disponible l'API de cette classe directement
		$this->api['versioncontrol'] = $this;
		
		// TODO Déclarer les commandes de l'API CLI pour ce plugin
	
	}
	
	/**
	 * (non-PHPdoc)
	 * @see Soho_Plugin::init()
	 */
	public function init() {
		
		// Save directory
		$this->revcDir = WG::vars('revisions_folder');
		
		// Check directory
		if (!is_dir($this->revcDir) || !is_readable($this->revcDir)) {
			throw new WGFilesIOException("Invalid revision directory");
		}
		
		// Clean directory
		$this->revcDir = realpath($this->revcDir) . '/';

		// Counts the number of files revisions to get the index
		$this->revCount = sizeof(glob($this->revcDir . '/r*')) + 1;
		
		// Get files API
		$this->files = WG::files();
		
	}
	
	/**
	 * Renvoi le chemin vers le fichier de révision.
	 * Retourne FALSE si une erreur survient, e.g. si le fichier n'existe pas.
	 * 
	 * @param int $revisionID
	 * @return string|false
	 */
	public function realpath($revisionID) {
		return realpath($this->revcDir . '/r' . $revisionID);
	}
	
	/**
	 * Purger le cache pour le noeud donné.
	 *
	 * Cette classe utilise un système interne de mise en cache des données pour accélérer
	 * le traitement successifs sur des fichiers. Ce cache interne est normalement gếré automatiquement,
	 * mais il peut être utile dans certains cas de purger ce cache.
	 *
	 * @param string $node Chemin vers le fichier à purger.
	 * @return boolean Renvoi TRUE si un cache existait et a été supprimé, FALSE sinon.
	 */
	public function purge($node) {
		
		$node = $this->files->cleanpath($node, false);
		
		$nodeUID = $this->files->getNodeUID($node);
		
		$r = isset($this->revisions[$nodeUID]);
		
		unset($this->revisions[$nodeUID]);
		
		return $r;
		
	}
	
	/**
	 * Met à jour les versions d'un noeud.
	 * Le noeud peut être un fichier ou un dossier. Si c'est un dossier, le paramètre $recursive
	 * indique si l'opération doit se propager aux sous-éléments.
	 * 
	 * @param string $node Chemin vers le noeud.
	 * @param string $msg Message à enregistrer avec les version crées.
	 * @param string[] $tags
	 * @param boolean $recursive
	 * @param boolean $gitignore Indique si les fichiers .gitignore doivent être pris en compte.
	 * @throws WGFilesIOException En cas d'erreur I/O
	 * @throws WGFilesSecurityException Si l'utilisateur courant n'a pas le droit de supprimer le noeud.
	 * @return string[]|null La liste des noeuds qui ont été affectés.
	 */
	public function updateNode($node, $msg = '', $tags = array(), $recursive = false, $gitignore = true) {
		
		// L'interface de la méthode indique que $gitignore est boolean, mais
		// en interne cette variable est utilisée pour communiquer les règles d'ignore
		// quand la fonction est appelée recursivement. Donc quand $gitignore vaut
		// TRUE, il est remplacé par un tableau vide qui servira à contenir les règles.
		if ($gitignore === true) {
			$gitignore = array();
		}
		
		// On vérifie que $node soit un chemin valide
		// En cas d'erreur, une exception WGFilesIOException sera levée
		// Au passage, $node est nettoyé
		$node = $this->files->checkNode($node, null, true);
		
		// Chemin réel vers le fichier
		$path = $this->files->getRootDirectory() . $node;
		
		// Si le chemin est invalide, la méthode lêve une exception
		if (!is_readable($path)) {
			throw new WGFilesIOException("Updated node not readable: $node");
		}
		
		// Tableau de sortie
		$r = array();
		
		// Pour les liens, on ne va pas suivre pour le moment car la récursivité
		// est pas loin... dans l'idéal, il faudrait mémoriser les éléments vus
		// et vérifier à chaque fichier... relou..
		// TODO Apporter une meilleur gestion des liens
		if (is_link($path)) {
			return $r;
		}
		
		// Application des règles d'ignore
		if (is_array($gitignore)) {
			// On parcours les règles des fichiers ignorés
			foreach ($gitignore as $rule) {
				// La comparaison se fait avec fnmatch
				if (fnmatch($rule, $node)) {
					//echo "GIT IGNORE: $node (rule: $rule)\n";
					return $r;
				}
			}
		}
		
		// Cas des répertoires
		if (is_dir($path)) {
			
			// Si le traitement récursif n'est pas activé on ne parcours pas le dossier
			if (!$recursive) {
				return array();
			}
			
			// On recupère la liste des éléments dans le dossier
			$list = scandir($path);
			
			// Traitement d'erreur
			if (!is_array($list)) {
				throw new WGFilesIOException("Unable to fetch updated node: $node");
			}
			
			// On retire . et ..
			array_shift($list);
			array_shift($list);
			
			// On parcours les sous-éléments
			foreach ($list as $item) {
				
				// Il s'agit d'un fichier .gitignore : on va le parser pour
				// complèter la liste des fichiers ignorés
				if (is_array($gitignore) && $item == '.gitignore') {
					// Lecture du fichier gitignore
					$fg = file_get_contents("$path/.gitignore");
					if ($fg) {
						// Explosion en lignes
						$fg = explode("\n", $fg);
						// Chaque ligne est traitée
						foreach ($fg as $fl) {
							$fl = trim($fl);
							// Lignes vides ou de commentaires : ignorées
							if ($fl == '' || substr($fl, 0, 1) == '#') {
								continue;
							}
							// On garde les directives ignore
							$gitignore[] = "$node/$fl";
							//echo "GIT ADD: $node/$fl\n";
						}
					}
					unset($fg, $fl);
				}
				
				// On recupère les mises à jour du sous-élément
				$update = $this->updateNode($node . '/' . $item, $msg, $tags, $recursive, $gitignore);
				
				// On mélange les deux tableaux
				$r = array_merge($r, $update);
				
			}
			
		}
		
		// Cas des fichiers
		else if (is_file($path)) {

			// On s'assure que le nom du path ne finisse pas par un slash
			if (substr($node, -1) === '/') {
				$node = substr($node, 0, -1);
			}
			
			// On détermine le Node UID
			$nodeUID = $this->files->getNodeUID($node);
			
			// On recupère les révisions du fichier
			$revisions = $this->getFileRevisions($nodeUID);
			
			// On calcule le checksum du fichier actuel
			$hash = md5_file($path);
			
			// Si des révisions existent, on compare avec la dernière
			if (sizeof($revisions) > 0) {
				
				// On recupère la dernière révision
				$lastRev = array_pop($revisions);
				
				// Le fichier est à jour avec sa dernière révision
				if ($lastRev['hash'] === $hash) {
					return $r;
				}
				
				// On remet la dernière révision dans le tas
				$revisions[] = $lastRev;
				
			}

			// Si on se trouve ici, c'est que la mise à jour doit se faire.
			// On détermine le chemin vers le fichier dans le dossier des révisions
			do {
				$revPath = $this->revcDir . '/r' . $this->revCount++;
			}
			while (file_exists($revPath));
			
			// On rajout le chemin dans le tableau de retour.
			$r[$node] = $this->revCount - 1;
			
			// Avant de créer les meta-données, on s'assure que les tags soient des
			// strings valides.
			foreach ($tags as $k => &$v) {
				$v = trim("$v");
				if (empty($v)) unset($tags[$k]);
			}
			
			// On fabrique les meta-données
			$meta = array(
				'revc' => $this->revCount - 1,		// Global revision number
				'date' => $_SERVER['REQUEST_TIME'],	// Date
				'size' => filesize($path),			// File size
				'hash' => $hash,					// Checksum
				'user' => WG::user()->get('login'),	// User ID
				'msg' => $msg,						// Message
				'tags' => $tags						// Tags
			);
			
			// On fait une copie du noeud dans le répertoire
			if (!copy($path, $revPath)) {
				throw new WGVersionControlException("Unable to copy revision: $node");
			}
			
			// On ajoute la nouvelle version dans les meta données
			$revisions[$meta['revc']] = $meta;
			
			// On met à jour les meta données
			$this->saveFileRevisions($nodeUID, $revisions);

		}
		
		return $r;
		
	}
	
	

	/**
	 * Returns an array with all revisions metadata for a given file.
	 *
	 * @param string $nodeUID
	 * @param boolean $usecache
	 * @throws WGVersionControlException
	 * @return mixed[]
	 */
	public function getFileRevisions($nodeUID, $usecache=true) {
	
		// Restore from cache
		if ($usecache && array_key_exists($nodeUID, $this->revisions)) {
			return $this->revisions[$nodeUID];
		}
	
		// Meta data file
		$metafile = $this->revcDir . '/{' . $nodeUID . '}';
	
		// Restore from meta file
		if (file_exists($metafile)) {
	
			// File get contents
			$data = file_get_contents($metafile);
			if (!$data) {
				throw new WGVersionControlException('Unable to read revision metadata file');
			}
	
			// JSON decode
			$data = json_decode($data, true);
			if (!is_array($data)) {
				throw new WGVersionControlException('Invalid revision metadata file');
			}

			// Create cache
			$this->revisions[$nodeUID] = $data;
	
			// Return revisions
			return $data;
	
		}
	
		// No revision
		return array();
	
	}
	
	/**
	 * This feature allows you to save metadata of a file. The created metadata file is located in the revision directory,
	 * and will name as the fuid. Data are encoded in JSON.
	 *
	 * @param string $nodeUID
	 * @param mixed[] $revisions
	 * @throws WGVersionControlException If writing in the meta file failed.
	 */
	protected function saveFileRevisions($nodeUID, $revisions) {
	
		// Update cache
		$this->revisions[$nodeUID] = $revisions;
	
		// Meta data file
		$metafile = $this->revcDir . '/{' . $nodeUID . '}';
	
		// Write meta file
		if (file_put_contents($metafile, json_encode($revisions)) === false) {
			throw new WGVersionControlException('Unable to write revision metadata file');
		}
	
	}
	
	/**
	 * Obtenir un diff entre deux contenus.
	 * 
	 * @param string $version1
	 * @param string $version2
	 * @return mixed[] En cas de succés.
	 * @return null En cas d'erreur (si la lib PEAR::Text_Diff n'est pas chargée).
	 */
	public static function getStringDiff($version1, $version2) {
		
		// Check library
		if (!class_exists('Text_Diff')) {
			return null;
		}
		
		// Create diff
		$diff = new Text_Diff('auto', array(explode("\n", $version1), explode("\n", $version2)));
		
		// Return diff
		return $diff->getDiff();
		
	}
	
	/**
	 * Renvoi le differentiel entre deux versions au format unidiff.
	 *
	 * @param string $version1
	 * @param string $version2
	 * @return string[] En cas de succés.
	 * @return null En cas d'erreur.
	 * @see http://en.wikipedia.org/wiki/Diff#Unified_format Détail du format unidiff.
	 */
	public static function getStringUnidiff($version1, $version2) {
		
		$diff = self::getStringDiff($version1, $version2);
		
		if (!is_array($diff)) {
			return null;
		}
		
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
					$out = "\n+" . implode("\n+", $d->final);
					$s2 = sizeof($d->final);
					$l2 += $s2;
					break;
				case 'Text_Diff_Op_change' :
					$out = "\n-" . implode("\n-", $d->orig);
					$s1 = sizeof($d->orig);
					$l1 += $s1;
					$out .= "\n+" . implode("\n+", $d->final);
					$s2 = sizeof($d->final);
					$l2 += $s2;
					break;
				case 'Text_Diff_Op_delete' :
					$out = "\n-" . implode("\n-", $d->orig);
					$s1 = sizeof($d->orig);
					$l1 += $s1;
					break;
			}
			$r[] = "\n@@ -$l1,$s1 +$l2,$s2 @@$out";
		}
		
		return $r;
	}

}

class WGVersionControlException extends WGPluginException { }

// On installe le plugin dans WG
WG::addPlugin(new Soho_Plugin_Files_VersionControl());

?>