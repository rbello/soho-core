<?php

/**
 * 
 * Gestion du service CRON
 * 
 */
class Soho_Plugin_Cron extends Soho_PluginBase {

	/**
	 * Configurations des CRON jobs
	 * @var mixed[][]
	 */
	protected $cronjobs = array();
	
	/**
	 * Constructeur de la classe.
	 */
	public function __construct() {

		// On renseigne le nom du plugin
		parent::__construct('cron');
	
		// On rends disponible l'API de cette classe directement
		$this->api['crons'] = $this;
		$this->api['crondata'] = $this;
		$this->api['cronjobs'] = $this;
		$this->api['executeCronJob'] = $this;
		$this->api['executeCronService'] = $this;
		$this->api['getCronJobsMinFrenquency'] = $this;
		
		// TODO Déclarer les commandes de l'API CLI pour ce plugin
	
	}
	
	/**
	 * (non-PHPdoc)
	 * @see Soho_Plugin::init()
	 */
	public function init() {
	
		// On parcours les modules disponibles
		foreach (WG::modules() as $moduleName => $module) {
	
			// On regarde si le manifest déclare des cronjobs
			if (!isset($module['cronjobs'])) continue;
				
			// On vérifie que le manifest soit valide
			if (!is_array($module['cronjobs'])) {
				throw new WGException("Invalid 'cronjobs' manifest configuration in module `$moduleName`");
			}
			
			// On parcours ces cronjobs
			foreach ($module['cronjobs'] as $jobName => $config) {
				$config['name'] = $jobName;
				$config['module'] = $moduleName;
				// On enregistre les données du cronjob
				$this->cronjobs[] = $config;
			}
	
		}
	
	}
	
	/**
	 * Renvoi la liste des noms de jobs.
	 * 
	 * @param $disabled Indique si les jobs désactivés doivent apparaitre. False par défaut.
	 * @return string[]
	 */
	public function getCronJobsList($disabled = false) {
		$r = array();
		foreach ($this->cronjobs as $job) {
			if (!$disabled && isset($job['disabled']) && $job['disabled'] === true) {
				continue;
			}
			$r[] = $job['name'];
		}
		return $r;
	}
	
	/**
	 * @shortcut WG::cronjobs()
	 * @shortcut WG::crondata()
	 * @shortcut WG::getCronJobsMinFrenquency()
	 */
	public function __handleGet($apiName) {
		if ($apiName == 'cronjobs') {
			return $this->cronjobs;
		}
		if ($apiName == 'crondata') {
			return $this->getCronData();
		}
		if ($apiName == 'getCronJobsMinFrenquency') {
			return $this->getCronJobsMinFrenquency();
		}
		if ($apiName == 'crons') {
			return $this;
		}
		throw new WGException("API not supported: $apiName (in Soho_Plugin_Cron)");
	}
	
	/**
	 * Renvoi le delai minimum d'exécution parmi tous les jobs.
	 * Cette méthode permet de déterminer le délai minimum d'exécution
	 * du service : c'est le plus petit délai qui soit associé à
	 * un job.
	 * Renvoi -1 si aucun job n'est programmé.
	 *
	 * @return int Le délai en secondes.
	 * @shortcut WG::getCronJobsMinFrenquency()
	 */
	public function getCronJobsMinFrenquency() {
		$min = 99999999999999;
		foreach ($this->cronjobs as $job) {
			$frequency = frency2sec($job['frequency']);
			if ($frequency > 0) {
				$min = min($min, $frequency);
			}
		}
		return $min === 99999999999999 ? -1 : $min;
	}
	
	/**
	 * Execute un job CRON.
	 * 
	 * @param string $jobName Le nom du job à lancer.
	 * @param boolean $echo Afficher les logs du service vers la sortie standard. Vaut false par défaut.
	 * @param boolean $savelog Enregistrer les logs du service dans le fichier cronlog. Vaut true par défaut.
	 * @return boolean Pour indiquer si la tâche a bien été executée (mais pas forcément qu'il n'y ai pas eu d'erreur)
	 * @shortcut WG::executeCronJob()
	 */
	public function __handle_executeCronJob($jobName, $echo=false, $savelog=true) {
		
		// On parcours les jobs
		foreach ($this->cronjobs as $job) {
			
			// Si le job ne corresponds pas on le saute
			if ($job['name'] !== $jobName) {
				continue;
			}
			
			// Le job a été trouvé, on commence par afficher toutes les erreurs
			error_reporting(E_ALL);
			
			// Ensuite on n'impose aucune limite au temps d'execution de ce job
			set_time_limit(0);
			
			// On ouvre un buffer
			ob_start();
			echo date('r') . ' *** Run '.$job['script']."\n";
			
			// TODO This job is disabled, you must have flag 'S' to avoid this limitation
			
			// Lancement du job
			try {
				include WG::base($job['script']);
			}
			catch (Exception $ex) {
				echo date('r') . ' [Cronjob ' . $jobName . '] '. get_class($ex) . ': ' . $ex->getMessage() . "\n";
			}
		
			// Update cron data
			$crs_data = $this->getCronData();
			$crs_data['job_' . $jobName] = $_SERVER['REQUEST_TIME'];
			$this->setCronData($crs_data);
		
			// On ferme le buffer
			$contents = ob_get_contents() . "\n";
			ob_end_clean();
			
			// On enregistre dans les logs
			if ($savelog) {
				$fp = fopen(WG::base('data/cron.log'), 'a');
				if (is_resource($fp)) {
					fwrite($fp, $contents);
					fclose($fp);
				}
			}
			
			// On affiche les logs
			if ($echo) {
				echo $contents;
			}
			
			// On remet le niveau d'erreur initial
			WG::resetErrorReportingLevel();
			
			// On renvoi true
			return true;
		}
		
		// Le job n'a pas été trouvé, on renvoi false
		return false;
	}
	
	
	/**
	 * Exécute le service CRON.
	 *
	 * Le service CRON permet d'exécuter automatiquement des scripts (jobs)
	 * selon un cycle défini à l'avance.
	 *
	 * TODO Pourquoi on utilise pas self::executeCronJob() ici ? Peut-être pour ne pas lancer 1000 buffers ?
	 *
	 * @param boolean $echo Afficher les logs du service vers la sortie standard. Vaut false par défaut.
	 * @param boolean $savelog Enregistrer les logs du service dans le fichier cronlog. Vaut true par défaut.
	 * @return int Renvoi le nombre de jobs qui ont été exécutés par le service.
	 * @shortcut WG::executeCronService()
	 */
	public function __handle_executeCronService($jobName, $crs_echo=false, $crs_savelog=true) {
		
		// On recupère les données du service CRON
		$crs_data = $this->getCronData();
		
		// Si le service n'a pas besoin d'être lancé, on évite ce lancement
		if ($crs_data['_service'] + $this->getCronJobsMinFrenquency() > $_SERVER['REQUEST_TIME']) {
			return false;
		}
		
		// On affiche toutes les erreurs
		error_reporting(E_ALL);
		
		// On ne met aucune limite de temps à l'execution de cette tâche
		set_time_limit(0);
		
		// On démarre un buffer
		ob_start();
		echo date('r') . " Cron service started...\n";
		
		// Compteur de jobs lancés
		$crs_c = 0;
		
		// On parcours les jobs
		foreach ($this->cronjobs as $job) {
			
			// Si le job est désactivé, on le saute
			if (isset($job['disabled']) && $job['disabled'] === true) {
				continue;
			}
			
			// Le nom du job
			$crs_jobname = $job['name'];
			
			// La fréquence du job, convertie en secondes
			$frequency = frency2sec($job['frequency']);
			
			// En cas d'erreur de la fréquence, on saute
			if ($frequency < 1) {
				continue;
			}
			
			// Si le job a déjà été lancé, on vérifie qu'il n'est pas relancé trop tot
			if (isset($crs_data['job_' . $crs_jobname])) {
				if ($crs_data['job_' . $crs_jobname] + $frequency > $_SERVER['REQUEST_TIME']) {
					continue;
				}
			}
			
			// Lancement du job
			echo date('r') . ' *** Run '.$job['script']."\n";
			try {
				include WG::base($job['script']);
			}
			catch (Exception $ex) {
				echo date('r') . ' [Cronjob ' . $crs_jobname . '] '. get_class($ex) . ': ' . $ex->getMessage() . "\n";
			}
			
			echo date('r') . " *** Finished: $crs_jobname \n";
			
			// On met à jour la date d'execution du job
			$crs_data['job_' . $crs_jobname] = $_SERVER['REQUEST_TIME'];
			
			// On incrémente le compte de jobs
			$crs_c++;
		}
		
		echo date('r') . " Cron service finished ($crs_c job(s))...\n";
		
		// On met à jour la date d'execution du service
		$crs_data['_service'] = time();
		
		// On enregistre les données du service CRON
		$this->setCronData($crs_data);
		
		// On ferme le buffer
		$contents = ob_get_contents() . "\n";
		ob_end_clean();
		
		// Enregistrement dans les logs
		if ($crs_savelog) {
			$fp = fopen(WG::base('data/cron.log'), 'a');
			if (is_resource($fp)) {
				fwrite($fp, $contents);
				fclose($fp);
			}
		}
		
		// Echo
		// TODO Ici il y a un bug avec $crs_echo, pour être certain que les
		// logs ne s'affichent pas j'ai rajouté un test sur dev_mode qui ne devrait pas être là
		if ($crs_echo === true && WG::vars('dev_mode') === true) {
			echo $contents;
		}
		
		// On remet le niveau d'erreur initial
		WG::resetErrorReportingLevel();
		
		// On renvoi le nombre de jobs executés
		return $crs_c;
	}
	
	/**
	 * TODO Cache
	 */
	public function getCronData() {
		$store = ModelManager::get('Store')->getByName('crondata', 1);
		return $store ? $store->data : array('_service' => 0);
	}
	
	/**
	 * TODO Cache
	 */
	public function setCronData($data) {
		
		$store = ModelManager::get('Store')->getByName('crondata', 1);
		
		// Création du cache s'il n'existe pas
		if (!$store) {
			$store = ModelManager::get('Store')->new
				->set('name', 'crondata')
				->set('item', 'main');
		}
		
		// Modification du cache
		$store
			->set('update', $_SERVER['REQUEST_TIME'])
			->set('data', $data);
		
		// Enregistrement du cache
		return $store->save();
		
	}
	
}

// On installe le plugin dans WG
WG::addPlugin(new Soho_Plugin_Cron());

?>