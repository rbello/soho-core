<?php

/**
 * 
 * Gestion des données étendues du système de fichier
 * 
 * Globalement, cette classe NE GERE PAS LES ACL ! C'est à vous d'implémenter la sécurité
 * avant l'appel aux méthodes de cette classe.
 * 
 */
class Soho_Plugin_ExFs extends Soho_PluginBase {

	/**
	 * Cache mémorisant les données d'EXFS.
	 * Il s'agit de meta données associés à des noeuds, qui permet de stoquer des infos comme
	 * le propriétaire du fichier, le groupe associé, des properties, ...
	 * @var mixed[]
	 */
	protected static $cache_exfs = null;
	
	/**
	 * Constructeur de la classe.
	 */
	public function __construct() {

		// On renseigne le nom du plugin
		parent::__construct('exfs');
	
		// On rends disponible l'API de cette classe directement
		$this->api['exfs'] = $this;
		
		// TODO Déclarer les commandes de l'API CLI pour ce plugin
	
	}
	
	/**
	 * (non-PHPdoc)
	 * @see Soho_Plugin::init()
	 */
	public function init() {
		
	}
	
	/**
	 * Renvoi le chemin vers le fichier de ressources EXFS.
	 *
	 * @return string
	 */
	protected static function getResourceInfoPath() {
		return WG::base('data/.exfs');
	}
	
	/**
	 * Renvoi toutes les informations EXFS.
	 *
	 * Cette fonction est directement adaptée depuis SabreDAV (ExtFs/Node.php)
	 * pour rendre compatible les deux systèmes.
	 *
	 * @return mixed[]
	 * @throws WGFilesIOException En cas d'erreur I/O
	 */
	public static function getResourceData() {
	
		// On recupère le chemin vers le fichier de ressources
		$path = self::getResourceInfoPath();
	
		// Si le fichier n'existe pas ce n'est pas grave, c'est qu'il n'a pas encore été
		// créé. On renvoi un tableau avec les données par défaut.
		// Par défaut, tout appartient au root.
		if (!file_exists($path)) {
			return array('/' => 
				array('own' => 'root', 'grp' => '-')
			);
		}
	
		// Ouverture du fichier
		$handle = fopen($path, 'r');
	
		// Impossible d'ouvrir le fichier, il existe mais son ouverture en lecture
		// est foirée. C'est une exception.
		if (!is_resource($handle)) {
			throw new WGFilesIOException("Unable to open EXFS data file");
		}
	
		// Création d'un LOCK partagé en lecture. Cette opération peut rater ce
		// n'est pas dramatique.
		@flock($handle, LOCK_SH);
	
		// On prépare une variable qui contiendra le contenu du fichier
		$data = '';
	
		// Lecture du fichier jusqu'à la fin
		while (!feof($handle)) {
			$data .= fread($handle, 8192);
		}
	
		// Fermeture du fichier
		fclose($handle);
	
		// Libération de la ressource.
		// Note: PHP 5.3.2 : Le déverrouillage automatique lorsque la ressource de fichiers
		// est fermée a été supprimée. Le déverrouillage doit maintenant être effectuée
		// manuellement.
		@flock($handle, LOCK_UN | LOCK_NB);
	
		// Désérialisation des données
		$data = unserialize($data);
	
		// Traitement d'erreur
		if (!is_array($data)) {
			throw new WGFilesIOException("Invalid EXFS data, unable to restore");
		}
	
		// On renvoi les données
		return $data;
	
	}
	
	/**
	 * Updates the resource information
	 *
	 * @param mixed[] $newData
	 * @return void
	 */
	protected static function putResourceData($newData) {
	
		// On recupère le chemin vers le fichier de ressources
		$path = self::getResourceInfoPath();
	
		// Ouverture du fichier en lecture et écriture ; place le pointeur de fichier à la
		// fin du fichier. Si le fichier n'existe pas, on tente de le créer.
		$handle = fopen($path, 'a+');
		
		// Impossible d'ouvrir le fichier
		if (!is_resource($handle)) {
			throw new WGFilesIOException("Unable to open EXFS data file");
		}
		
		// Obtention d'un verrou exclusif en écriture. Si cette opération échoue, on
		// ne stope pas le processus, on verra bien, au pire il y aura des anomalies.
		@flock($handle, LOCK_EX);

		// On remet le fichier à zero, et un place le curseur au début du fichier
		ftruncate($handle,0);
		rewind($handle);

		// On écrit les données 
		if (fwrite($handle, serialize($newData)) === false) {
			// En cas d'erreur, on ferme le fichier, on libère la ressource
			fclose($handle);
			flock($handle, LOCK_UN | LOCK_NB);
			// Et on lève une exception
			throw new WGFilesIOException("Unable to write EXFS data file");
		}
		
		// On ferme le fichier et on retire le verrou
		fclose($handle);
		@flock($handle, LOCK_UN | LOCK_NB);
	
	}
	
	/**
	 * Supprimer les données EXFS d'un noeud.
	 * 
	 * @param string $node Chemin vers le noeud de données.
	 * @return boolean
	 */
	public function deleteExFsData($node) {
		
		// Si le cache n'existe pas, on tente de l'initialiser
		if (!self::$cache_exfs) {
			self::$cache_exfs = self::getResourceData();
		}

		// Si le noeud a des EXFS data
		if (isset(self::$cache_exfs[$node])) {
			
			// On supprime les données
			unset(self::$cache_exfs[$node]);
			
			// Et on enregistre les données
			self::putResourceData(self::$cache_exfs);
			
			// OK
			return true;
			
		}
		
		// Si non, on renvoi false
		return false;
		
	}
	
	/**
	 * Renvoi les propriétés EXFS d'un noeud.
	 * 
	 * Si le noeud ne possède aucune données, cette méthode renvoi un tableau contenant les
	 * données par défaut des nodes.
	 * 
	 * @param string $node Chemin vers le noeud de données.
	 * @param null|string $property Nom d'une propriété particulière à renvoyer.
	 * @return mixed[] Si $property n'est pas renseigné.
	 * @return mixed|null Si $property est demandé.
	 */
	public function getExFsData($node, $property = null) {
	
		// Si le cache n'existe pas, on tente de l'initialiser
		if (!self::$cache_exfs) {
			self::$cache_exfs = self::getResourceData();
		}
		
		// On regarde si le noeud ne possède pas des données EXFS
		if (!isset(self::$cache_exfs[$node])) {
			// On se prépare à renvoyer les données par défaut
			$r = array(
				'own' => null,
				'-' => null
			);
		}
		
		// Si il en possède, on utilise les bonnes données
		else {
			$r = self::$cache_exfs[$node];
		}
		
		// Ensuite, si l'owner ou ne group n'est pas renseigné, on lance un processus
		// récursif pour remonter l'arborescence du noeud et récupérer les infos
		// du premier parent qui en spécifie.
		if (!isset($r['own']) || !isset($r['grp'])) {
			
			// On explose le chemin avec le slash comme délimiteur, et on retire le dernier token
			// qui corresponds au chemin actuel qu'on a déjà recherché.
			$tokens = explode('/', $node);
			array_pop($tokens);

			// On parcours les tokens
			while (sizeof($tokens) > 0) {
				
				// On recompose le chemin vers le parent
				$path = implode('/', $tokens);
				
				// On est arrivé à la fin
				if (empty($path)) {
					$path = '/';
				}
				
				// On regarde si des données EXFS sont renseignéées
				if (isset(self::$cache_exfs[$path])) {
					
					// On recupère les données du parent
					$parentData = self::$cache_exfs[$path];
					
					// Si le noeud n'a pas de owner et que son parent en a un, on le prends
					if (!isset($r['own']) && isset($parentData['own'])) {
						$r['own'] = '&' . $parentData['own'];
					}
					
					// Si le noeud n'a pas de groupe et que son parent en a un, on le prends
					if (!isset($r['grp']) && isset($parentData['grp'])) {
						$r['grp'] = '&' . $parentData['grp'];
					}

					// Si les deux données existent maintenant, on arrête là
					if (isset($r['own']) && isset($r['grp'])) {
						break;
					}

				}
				
				// A chaque tour on retire un token
				array_pop($tokens);
				
			}
			
		}
		
		// Si on a demandé une property en particulier, on la renvoi ou NULL si elle
		// n'existe pas. Comme une property peut aussi avoir NULL comme valeur, pour
		// distinguer les deux il faut mieux ne pas spécifier d'argument $property
		// et recherché la clé dans le tableau renvoyé.
		if (is_string($property)) {
			return isset($r[$property]) ? $r[$property] : null;
		}
		
		// A la fin, on renvoi le tableau de données
		return $r;
	}
	
	/**
	 * Modifier une valeur EXFS d'un noeud.
	 * 
	 * @param string $node
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	public function setExFsData($node, $key, $value) {

		// On recupère les données EXFS
		$data = self::getResourceData();
		
		// On regarde si le noeud ne possède pas des données EXFS
		if (!isset($data[$node])) {
			// On commence par renseigner les données par défaut
			$data[$node] = array(
				'own' => null,
				'grp' => null
			);
		}
		
		// On fait la modification
		$data[$node][$key] = $value;
		
		// On met à jour le cache
		self::$cache_exfs = $data;
		
		// Et on enregistre les données
		self::putResourceData($data);
		
	}

}

// On installe le plugin dans WG
WG::addPlugin(new Soho_Plugin_ExFs());

?>