<?php

class Soho_CLI_WG extends Soho_CLI_Files {	
	
	/**
	 * @cmdAlias modlist
	 */
	function handle_modules($file, $cmd, $params, $argv) {
		return $this->handle_modlist($file, $cmd, $params, $argv);
	}
	
	/**
	 * List of installed modules.
	 *
	 * @requireFlags uS
	 * @allowedParams
	 * @cmdPackage Application data
	 */
	function handle_modlist($file, $cmd, $params, $argv) {
		if (!$this->check()) {
			return false;
		}
		echo "MODULE               VIEW WS  CRON VERSION VENDOR" . PHP_EOL;
		echo "-----------------------------------------------------" . PHP_EOL;
		$c = 0;
		foreach (WG::modules() as $module) {
			echo str_pad(substr($module['moduleName'], 0, 20), 21);
			echo isset($module['views']) ? str_pad(sizeof($module['views']), 5) : '0    ';
			echo isset($module['webservices']) ? str_pad(sizeof($module['webservices']), 4) : '0   ';
			echo isset($module['cronjobs']) ? str_pad(sizeof($module['cronjobs']), 5) : '0    ';
			echo str_pad($module['moduleVersion'], 8);
			echo $module['vendorName'];
			echo PHP_EOL;
			$c++;
		}
		echo "Total: $c" . PHP_EOL;
		return true;
	}
	
	/**
	 * @cmdAlias viewlist
	 */
	function handle_views($file, $cmd, $params, $argv) {
		return $this->handle_viewlist($file, $cmd, $params, $argv);
	}
	
	/**
	 * List of installed views.
	 *
	 * @requireFlags uS
	 * @allowedParams
	 * @cmdPackage Application data
	 */
	function handle_viewlist($file, $cmd, $params, $argv) {
		if (!$this->check()) {
			return false;
		}
		echo "MODULE               VIEW                 SECURITY   DISTRIBUTION SCRIPT" . PHP_EOL;
		echo "------------------------------------------------------------------------------" . PHP_EOL;
		$c = 0;
		foreach (WG::views() as $view) {
			echo str_pad(substr($view['module'], 0, 20), 21);
			echo str_pad(substr($view['name'], 0, 20), 21);
			echo str_pad(substr(isset($view['requireFlags']) ? $view['requireFlags'] : 'Public', 0, 10), 11);
			echo str_pad(substr(isset($view['distribution']) ? $view['distribution'] : 'CACHE', 0, 12), 13);
			echo '/' . $view['script'];
			echo PHP_EOL;
			$c++;
		}
		echo "Total: $c" . PHP_EOL;
		return true;
	}
	
	/**
	 * @cmdAlias varlist
	 */
	function handle_vars($file, $cmd, $params, $argv) {
		return $this->handle_varlist($file, $cmd, $params, $argv);
	}
	
	/**
	 * Fetch application config vars.
	 *
	 * @requireFlags uZ
	 * @allowedParams
	 * @cmdPackage Application data
	 */
	function handle_varlist($file, $cmd, $params, $argv) {
		if (!$this->check()) {
			return false;
		}
		echo "NAMESPACE  VAR                             EDIT TYPE     VALUE" . PHP_EOL;
		echo "------------------------------------------------------------------" . PHP_EOL;
		$c = 0;
		foreach (WG::vars_raw() as $k => $v) {
			
			// Namespace
			echo str_pad(substr($v['ns'], 0, 10), 11);
			
			// Varname
			echo str_pad(substr("$k", 0, 31), 32);

			// Overridable
			echo (isset($v['overridable']) && $v['overridable'] === true) ? 'YES  ' : 'NO   ';
			
			// Valeur : mot de passe
			if (isset($v['isPassword']) && $v['isPassword'] === true) {
				$v = str_repeat('*', strlen($v['value']));
				$t = 'password';
			}
			// Valeur : array
			else if (is_array($v['value'])) {
				$v = '['.@implode(', ', $v['value']).']';
				$t = 'array';
			}
			// Valeur : boolean
			else if (is_bool($v['value'])) {
				$v = $v['value'] ? 'TRUE' : 'FALSE';
				$t = 'bool';
			}
			// Valeur : autres
			else {
				$t = isset($v['type']) ? $v['type'] : gettype($v['value']);
				$v = $v['value'];
			}

			// Type / Valeur
			echo str_pad(substr($t, 0, 8), 9);
			echo substr($v, 0, 80);

			echo PHP_EOL;
			$c++;
		}
		echo "Total: $c" . PHP_EOL;
		return true;
	}
	
	/**
	 * @cmdAlias cronlist
	 */
	function handle_crons($file, $cmd, $params, $argv) {
		return $this->handle_cronlist($file, $cmd, $params, $argv);
	}
	
	/**
	 * List of scheduled cron jobs.
	 *
	 * @requireFlags a
	 * @allowedParams
	 * @cmdPackage Application data
	 */
	function handle_cronlist($file, $cmd, $params, $argv) {
		if (!$this->check()) {
			return false;
		}
		$data = WG::crondata();
		echo "Last execution: " . ($data['_service'] > 0 ? WG::rdate($data['_service']) : 'never') . PHP_EOL;
		echo "MODULE               JOB NAME             MOD FREQUENCY    LAST RUN               SCRIPT" . PHP_EOL;
		echo "----------------------------------------------------------------------------------------" . PHP_EOL;
		$c = 0;
		foreach (WG::cronjobs() as $job) {
			$disabled = (isset($job['disabled']) && $job['disabled'] === true);
			echo str_pad(substr($job['module'], 0, 20), 21);
			echo str_pad(substr($job['name'], 0, 20), 21);
			echo (isset($job['disabled']) && $job['disabled'] === true) ? 'OFF ' : '-   ';
			echo str_pad(substr($job['frequency'], 0, 12), 13);
			echo str_pad(substr((isset($data['job_'.$job['name']]) ? WG::rdate($data['job_'.$job['name']]) : 'never'), 0, 22), 23);
			echo '/' . $job['script'];
			echo PHP_EOL;
			$c++;
		}
		echo "Total: $c" . PHP_EOL;
		return true;
	}
	
	
	/**
	 * Reloads the configuration file of the system.
	 *
	 * @requireFlags a
	 * @allowedParams
	 * @cmdPackage Application data
	 * @cmdUsage reload
	 */
	function handle_reload($file, $cmd, $params, $argv) {
		if (!$this->check()) {
			return false;
		}
		echo "Application cache is: " . (WG::vars('enable_app_cache') === true ? $this->green('On') : $this->red('Off')) . PHP_EOL;
		echo "Remove configuration cache...                    ";
		$db = WG::database();
		$query = 'DELETE FROM `'.$db->getDatabaseName().'`.`'.$db->getPrefix()."store` WHERE `name` = 'app-cache' LIMIT 1";
		try {
			$deleted = $db->query($query);
			if (!is_int($deleted) || $deleted < 1) {
				throw new Exception('Nothing to clear');
			}
		}
		catch (Exception $ex) {
			echo $this->failure() . PHP_EOL;
			echo ' ' . $ex->getMessage() . PHP_EOL;
			return false;
		}
		echo $this->ok() . PHP_EOL;
		return true;
	}

	
	/**
	 * Application information.
	 *
	 * @requireFlags a
	 * @allowedParams a
	 * @cmdPackage Application data
	 * @cmdUsage ${cmdname} [-a]
	 */
	function handle_info($file, $cmd, $params, $argv) {
		if (!$this->check()) {
			return false;
		}
		if (isset($params['help'])) {
			echo "Usage: $cmd [-a]" . PHP_EOL;
			return true;
		}
		echo "Host: " . WG::host() . " (" . (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost') . ')' . PHP_EOL;
		echo "PHP: " . phpversion() . PHP_EOL;
		echo "Workgroop: " . WG::vars('appVersion') . ' (API ' . self::VERSION . ')' . PHP_EOL;
		echo "OS: " . php_uname('a') . PHP_EOL;
		echo "ServerTime: " . date('r') . " (" . date_default_timezone_get() . ")" . PHP_EOL;
		echo "Application cache: " . (WG::vars('enable_app_cache') === true ? 'On' : 'Off') . PHP_EOL;
		echo "Execution time max: " . ini_get('max_execution_time') . PHP_EOL;
		echo "Magic quotes GPC: " . (get_magic_quotes_gpc() ? 'On' : 'Off') . PHP_EOL;
		echo "Debug: dev_mode=" . (WG::vars('dev_mode') ? 'On' : 'Off') . " error_reporting=" . error_reporting() . PHP_EOL;
		if (isset($params['a'])) {
			echo "PHP Extensions: " . implode(', ', get_loaded_extensions()) . PHP_EOL;
		}
		return true;
	}

	/**
	 * Execute cron service of specific jobs.
	 *
	 * @requireFlags S
	 * @allowedParams help
	 * @cmdPackage Application data
	 */
	function handle_cronrun($file, $cmd, $params, $argv) {
		if (!$this->check()) {
			return false;
		}
		if (isset($params['help'])) {
			echo "Usage: $cmd" . PHP_EOL;
			echo "Usage: $cmd [JOBNAME] [JOBNAMES..]" . PHP_EOL;
			return false;
		}
		// Run specific cron job
		if (isset($params[0])) {
			foreach ($params as $k => $jobname) {
				// TODO Si le job est désactivé, il faudrait le flag Z pour bypasser
				// Ou alors on gêre ça dans executeCronJob() ?
				if (!is_int($k)) continue;
				if (!WG::executeCronJob($jobname, true, true)) {
					echo "Failure: unable to run `$jobname` cron job!" . PHP_EOL;
				}
			}
		}
		// Run cron service
		else {
			echo "Execute cron service..." . PHP_EOL;
			WG::executeCronService(true, true);
		}
		return true;
	}
	
	/**
	 * Display host configuration name.
	 *
	 * @requireFlags u
	 * @allowedParams
	 * @cmdPackage Application data
	 */
	 function handle_host($file, $cmd, $params, $argv) {
		 if (!$this->check()) {
		 	return false;
		 }
		 echo "Current host: " . $this->bold(WG::host());
		 echo " (" . (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost') . ')' . PHP_EOL;
		 return true;
	 }
	 
	 /**
	  * Manage system logs.
	  *
	  * @requireFlags a
	  * @allowedParams help
	  * @cmdPackage Application data
	  */
	 function handle_logs($file, $cmd, $params, $argv) {
	 	
	 	if (!$this->check()) {
	 		return false;
	 	}
	 	
	 	if (isset($params['help'])) {
	 		echo "Usage: $cmd" . PHP_EOL;
	 		echo "Usage: $cmd raz FILENAME" . PHP_EOL;
	 		echo "Usage: $cmd archive FILENAME" . PHP_EOL;
	 		echo "Usage: $cmd print FILENAME" . PHP_EOL;
	 		return false;
	 	}
	 	
	 	if (isset($params[0])) {
	 		
	 		if (!isset($params[1])) {
	 			echo "Usage: $cmd {$params[0]} FILENAME" . PHP_EOL;
	 			return false;
	 		}
	 		
	 		$logfile = WG::base('data' . Soho_Plugin_Files::cleanpath($params[1]));
	 			
	 		if (!file_exists($logfile)) {
	 			echo "$cmd: file `{$params[1]}` is not readable" . PHP_EOL;
	 			return false;
	 		}
	 		
	 		// RAZ
	 		if ($params[0] == 'raz') {
	 			
	 			if (!WG::checkFlags('S')) {
	 				echo "$cmd: you are not allowed to raz log files" . PHP_EOL;
	 				return false;
	 			}
	 			
	 			if (!unlink($logfile)) {
	 				echo "$cmd: unable to delete log file" . PHP_EOL;
	 				return false;
	 			}
	 			
	 			return true;
	 			
	 		}
	 		
	 		// Archive
	 		else if ($params[0] == 'archive') {
	 			
	 			$newfile = dirname($logfile) . '/' . date('d-m-Y') . '.' . basename($logfile);
	 			
	 			if (!rename($logfile, $newfile)) {
	 				echo "$cmd: unable to rename log file" . PHP_EOL;
	 				return false;
	 			}
	 			
	 			return true;
	 			
	 		}
	 		
	 		// Print
	 		else if ($params[0] == 'print') {
	 			
	 			if (!WG::checkFlags('S')) {
	 				echo "$cmd: you are not allowed to read log files" . PHP_EOL;
	 				return false;
	 			}
	 			
	 			echo file_get_contents($logfile) . PHP_EOL;
	 			echo "File size: " . format_bytes(filesize($logfile)) . PHP_EOL;
	 			echo "Last modification: " . WG::rdate(filemtime($logfile)) . PHP_EOL;
	 			
	 			return true;
	 			
	 		}
	 		
	 		// Action impossible
	 		else {
	 			echo "$cmd: action `{$params[0]}` not suported" . PHP_EOL;
	 			return false;
	 		}
	 		
	 	}
	 	
	 	echo "FILENAME                            SIZE            LAST UPDATE                    INFO" . PHP_EOL;
	 	echo "-------------------------------------------------------------------------------------------" . PHP_EOL;
	 	
	 	$c = 0;
	 	
	 	// Recherche des fichiers de log
	 	foreach (glob(WG::base('data/*.log')) as $file) {
	 		
	 		if (!is_file($file)) continue;
	 		
	 		// Filename
	 		echo str_pad(substr(basename($file), 0, 35), 36);
	 		
	 		// Size
	 		echo str_pad(substr(format_bytes(filesize($file)), 0, 15), 16);
	 		
	 		// Last update
	 		echo str_pad(substr(WG::rdate(filemtime($file)), 0, 30), 31);
	 		
	 		// Info
	 		switch (basename($file)) {
	 			case 'cron.log' : echo 'Cron service logs.'; break;
	 			case 'wgcrt.log' : echo 'Security session manager logs.'; break;
	 			case 'wgcrt-debug.log' : echo 'Debug logs for WGCRT_Session system.'; break;
	 			case 'remote-cron.log' : echo 'Logs for remote trigger of the Cron service.'; break;
	 		}
	 		
	 		echo PHP_EOL;
	 		
	 		$c++;
	 		
	 	}
	 	
	 	echo "Total: $c" . PHP_EOL;
	 	
	 	
	 	return true;
	 }
	
}

?>