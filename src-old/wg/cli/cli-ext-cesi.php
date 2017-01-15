<?php

class Soho_CLI_Cesi extends Soho_CLI_Stats {	
	
	/**
	 * List of installed modules.
	 *
	 * @requireFlags u
	 * @allowedParams help update
	 * @cmdPackage CESI
	 */
	function handle_cesi($file, $cmd, $params, $argv) {
	
		if (!$this->check()) {
			return false;
		}
		
		// Aide
		if (!isset($params[0]) || isset($params['help'])) {
			echo "Usage: $cmd etudiant [option] [args]" . PHP_EOL;
			echo "Usage: $cmd etudiant list" . PHP_EOL;
			echo "Usage: $cmd etudiant add <lastname> <firstname> <email> <birthday> <formation nickname>" . PHP_EOL;
			return true;
		}
		
		$arg = strtolower($params[0]);
		
		if ($arg == "etudiant") {
			if (!isset($params[1])) {
				echo "Usage: $cmd etudiant [option]" . PHP_EOL;
				echo "Type '$cmd --help' for more details." . PHP_EOL;
				return true;
			}
			$arg = strtolower($params[1]);
			if ($arg == "list") return $this->do_etudiant_list($params, $argv);
		}
		
		if ($arg == "load") {
			return $this->do_load();
		}
		
		echo "Error: invalid argument '$arg'" . PHP_EOL;
		return false;
	}
	
	function do_load($params, $argv) {
		include WG::base('modules/cesi/data.php');
		return true;
	}

	function do_etudiant_list($params, $argv) {
	
		$etudiant = WG::model('Etudiant');
		$etudiants = $etudiant->all('*', 'formation');
		
		foreach ($etudiants as $e) {
			
			echo str_pad($etudiant->firstname . ' ' . $etudiant->lastname, 20);
			echo PHP_EOL;
			
		}
	
		return true;
	
	}
	
}

?>