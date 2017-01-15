<?php

class Soho_CLI {

	// Static
	const VERSION = '1.0.12';
	
	/**
	 * Ce code de retour permet d'indiquer au client qu'un mot de passe doit être renvoyé.
	 * Le client doit rappeler la même commande, en passant '--passwd="LePassword"' à la commande.
	 * @var int
	 */
	const INPUT_PWD = 254;
	
	/**
	 * Idem que pour INPUT_PWD, mais en plus le mot de passe doit être renvoyé en SHA1.
	 * C'est toujours le modifier '--passwd' qui doit renseigner le mot de passe.
	 * @var int
	 */
	const INPUT_PWD_SHA1 = 253;
	
	/**
	 * Ce code de retour permet d'indiquer au client qu'il doit renvoyer la concaténation d'un nom
	 * d'utilisateur et d'un mot de passe, le tout en SHA1. Cela corresponds à la norme d'enregistrement
	 * des mots de passes pour WGCRT.
	 * Le client doit renvoyer le résultat dans le modifier '--passwdcr'.
	 * La commande qui renvoi ce code doit aussi renseigner le champ 'concat' de la variable de contexte.
	 * @var int
	 */
	const INPUT_PWD_CONCAT_USER_SHA1 = 252;
	
	// Config
	public $allowIdentityAuto = false; // @deprecated
	public $allowIdentityOverride = false; // @deprecated

	/**
	 * Cette variable indique si la coloration AINSI est active
	 * TODO Faite plutôt un 'type' de décoration: ainsi, html, ... 
	 * @var boolean
	 */
	protected $styles = false;
	
	/**
	 * Cette variable est utilisable pour récupérer le résultat de l'exec.
	 * @var mixed[]
	 */
	public $result = null;
	
	/**
	 * Contient la liste des commandes.
	 * @var mixed[]
	 */
	protected $commands = array();
	
	/**
	 * 
	 */
	public function __construct() {
		
		// Initialisation des commandes
		foreach (get_class_methods($this) as $method) {
			if (substr($method, 0, 7) !== 'handle_' || substr($method, -13) == '_autocomplete') {
				continue;
			}
			$this->commands[substr($method, 7)] = $this->getcmddoc(substr($method, 7));
		}
			
	}
	
	/**
	 * @return boolean
	 * @throw Exception
	 */
	public function exec($argv) {
		
		$this->result = array(
			'failure' => true,
			'returnCode' => 1
		);

		// Split arguments
		if (!is_array($argv)) {
			$argv = self::splitCommand("$argv");
		}

		// Check arguments
		if (sizeof($argv) < 2) {
			echo "Usage: {$argv[0]} CMD [PARAM...]" . PHP_EOL;
			return false;
		}

		// Parse parameters
		$params = self::parseParameters($argv);
		
		// Global modifier: identity
		if ($this->allowIdentityAuto && isset($params['identity'])) {
			if ($params['identity'] == 'auto') {
				if (!$this->allowIdentityAuto) {
					echo "IdentityAuto: Forbidden by security policy." . PHP_EOL;
					return false;
				}
				if (isset($_SERVER['USER'])) {
					$params['identity'] = $_SERVER['USER'];
				}
				else if (isset($_SERVER['LOGNAME'])) {
					$params['identity'] = $_SERVER['LOGNAME'];
				}
				else {
					echo "Unable to retrieve user login using PHP globals." . PHP_EOL;
					return false;
				}
			}
			else if (!$this->allowIdentityOverride) {
				echo "IdentityOverride: Forbidden by security policy." . PHP_EOL;
				return false;
			}
			$user = ModelManager::get('TeamMember')->getByLogin($params['identity'], 1);
			if (!$user) {
				echo "User not found: " . $this->bold($params['identity']) . PHP_EOL;
				return false;
			}
			WGCRT_Session::startedSession()->login($user);
			unset($argv[array_search('--identity=' . $params['identity'], $argv)]);
			unset($params['identity']);
			//echo "User is: {$user->login} [UID={$user->id}]" . PHP_EOL;
		}

		// Shift parameters
		$file = array_shift($params);
		$cmd = array_shift($params);
		array_shift($argv);
		array_shift($argv);

		// Search command
		if (!method_exists($this, "handle_$cmd")) {
			
			echo "Command not found: " . $this->bold($cmd) . PHP_EOL;
			
			// Suggestion
			$metaphone = metaphone($cmd);
			$suggest = array();
			foreach ($this->commands as $method => $info) {
				// Sécurité: ne pas proposer les commandes que l'user n'a pas le droit d'utiliser
				if (isset($info['@requireFlags'])) {
					if (!WG::checkFlags($info['@requireFlags'][0])) {
						continue;
					}
				}
				// Rapprochement syntaxique
				if (levenshtein($cmd, $method) === 1) {
					$suggest[] = $method;
				}
				// Rapprochement phonique
				else if (metaphone($method) == $metaphone) {
					$suggest[] = $method;
				}
			}
			if (sizeof($suggest) > 0) {
				echo "Did you mean '" . implode("' or '", $suggest) . "' ?" . PHP_EOL;
			}
			
			return false;
		}

		// Context
		$context = array();
		
		// Call command
		$result = call_user_func_array(
			array($this, "handle_$cmd"),
			array($file, $cmd, $params, $argv, &$context)
		);
		
		// Check result
		if (is_bool($result)) {
			$this->result['failure'] = !$result;
			$this->result['returnCode'] = $result ? 0 : 1;
		}
		else if (is_int($result)) {
			$this->result['failure'] = $result !== 0;
			$this->result['returnCode'] = $result;
		}
		else if (is_string($result)) {
			$this->result['failure'] = false;
			$this->result['returnCode'] = 0;
			$this->result['returnValue'] = $result;
		}
		else {
			$this->result['failure'] = true;
			$this->result['returnCode'] = 1;
		}
		
		// On merge la variable de context, qui permet d'overrider
		// le contenu du résultat.
		$this->result = array_merge($this->result, $context);
		
		// Return success indicator
		return !$this->result['failure'];

	}

	// http://pueblo.sourceforge.net/doc/manual/ansi_color_codes.html
	public function setStylesEnabled($value) {
		$this->styles = (bool) $value;
	}

	function bold($str) {
		return $this->styles ? "\033[1m$str\033[22m" : $str;
	}

	function red($str) {
		return $this->styles ? "\033[31m$str\033[39m" : $str;
	}

	function green($str) {
		return $this->styles ? "\033[32m$str\033[39m" : $str;
	}

	function orange($str) {
		return $this->styles ? "\033[33m$str\033[39m" : $str;
	}
	
	function blue($str) {
		return $this->styles ? "\033[34m$str\033[39m" : $str;
	}

	function ok($str = 'OK') {
		return $this->styles ? "[\033[1m\033[32m$str\033[39m\033[22m]" : "[$str]";
	}

	function failure($str = 'FAILURE') {
		return $this->styles ? "[\033[1m\033[31m$str\033[39m\033[22m]" : "[$str]";
	}

	function warn($str = 'WARNING') {
		return $this->styles ? "[\033[1m\033[33m$str\033[39m\033[22m]" : "[$str]";
	}

	function underline($str) {
		return $this->styles ? "\033[4m$str\033[24m" : $str;
	}

	function checkModifiers($params, $modifiers) {
		if (!is_array($modifiers)) {
			$modifiers = preg_split('/\s+/', "$modifiers");
		}
		foreach ($params as $k => $v) {
			if (is_int($k)) continue;
			if (!in_array($k, $modifiers)) {
				echo "Invalid modifier: " . $this->bold($k) . PHP_EOL;
				return false;
			}
		}
		return true;
	}

	/**
	 * Décompose une châine de commande.
	 * 
	 * Cette méthode fait que:
	 *   - l'espace entre les commandes peut-être de plusieurs blancs
	 *   - les paramètres entre quotes avec des espaces sont bien pris comme en compte comme 1 argument 
	 * 
	 * @param string $str
	 * @return string[]
	 */
	public static function splitCommand($str) {
		// On sépare les tokens avec les caractères blancs, on rassemble en uniformisant
		// la séparation des tokens avec un simple espace, puis on utilise une fonction qui
		// va découper en prenant en compte les châines.
		return str_getcsv(implode(' ', preg_split('/\s+/', "$str")), ' ');
	}
	
	/**
	* Parses $GLOBALS['argv'] for parameters and assigns them to an array.
	*
	* Supports:
	* -e
	* -e <value>
	* --long-param
	* --long-param=<value>
	* --long-param <value>
	* <value>
	*
	* @param array $params List of parameters. Left null mean $GLOBALS['argv'].
	* @param array $all If FALSE, stop parsing parameters after the first command met.
	* @param array $noopt List of parameters without values.
	*/
	public static function parseParameters($params=null, $all=true, $noopt = array(), $allowShort=true) {
		$result = array();
		if (!is_array($params)) {
			$params = $GLOBALS['argv'];
		}
		$stopOpt = false;
		// could use getopt() here (since PHP 5.3.0), but it doesn't work relyingly
		reset($params);
		while (list($tmp, $p) = each($params)) {
			if ($p{0} == '-' && ($all || !$stopOpt) && strlen($p) > 1) {
				$pname = substr($p, 1);
				$value = true;
				if ($pname{0} == '-') {
					// long-opt (--<param>)
					$pname = substr($pname, 1);
					if (strpos($p, '=') !== false) {
						// value specified inline (--<param>=<value>)
						list($pname, $value) = explode('=', substr($p, 2), 2);
					}
				}
				// check if next parameter is a descriptor or a value
				if ($allowShort) {
					$nextparm = current($params);
					if (!in_array($pname, $noopt) && $value === true && $nextparm !== false && $nextparm{0} != '-') {
						list($tmp, $value) = each($params);
					}
				}
				$result[$pname] = $value;
			}
			else {
				$stopOpt = true;
				// param doesn't belong to any option
				$result[] = $p;
			}
		}
		return $result;
	}

	protected function getcmddoc($cmd, $parent=null, $redirect=array()) {
		try {
			$class = new ReflectionClass(get_class($this));
			$method = $class->getMethod('handle_' . $cmd);
			$doc = $method->getDocComment();
			if (is_string($doc)) {
				$c = '@doc';
				$r = array($c => array());
				$alias = array();
				foreach (explode("\n", substr($doc, 2, -2)) as $line) {
					$line = trim($line);
					while (substr($line, 0, 1) == '*') {
						$line = ltrim(substr($line, 1));
					}
					if (substr($line, 0, 1) == '@') {
						@list($c, $line) = explode(' ', $line, 2);
					}
					$r[$c][] = $line;
				}
				$r = self::atrim($r);
				if (isset($r['@cmdAlias'])) {
					if (in_array($cmd, $redirect)) {
						echo "Debug warning: commands alias loop between '$cmd' and '$parent'" . PHP_EOL;
						throw new Exception('commands alias loop between');
					}
					$redirect[] = $cmd;
					$r = array_merge($r, $this->getcmddoc($r['@cmdAlias'][0], $cmd, $redirect));
				}
				return $r;
			}
		}
		catch (Exception $ex) { }
		return null;
	}

	protected static function atrim($array) {
		if (is_array($array)) {
			foreach ($array as &$a) {
				if (is_array($a)) {
					// Right
					for ($i = sizeof($a) - 1; $i >= 0; $i--) {
						if ($a[$i] == '') unset($a[$i]);
						else break;
					}
					// Left
					foreach ($a as $k => $v) {
						if ($v == '') unset($a[$k]);
						else break;
					}
					$a = array_values($a);
				}
			}
		}
		return $array;
	}
	
	protected static function defaultFlags() {
		$flags = '';
		foreach (WG::flags() as $flag) {
			if (isset($flag['default']) && $flag['default']) {
				$flags .= $flag['flag'];
			}
		}
		return $flags;
	}

	protected function check($allowedParams='', $requiredFlags=null) {
		// Command data
		$traces = debug_backtrace();
		$traces = $traces[1];
		$cmd = substr($traces['function'], 7);
		$cmdinput = $traces['args'][1];
		$params = $traces['args'][2];
		$argv = $traces['args'][3];
		// Auto
		if ($allowedParams == '' || !is_string($requiredFlags)) {
			$doc = $this->commands[$cmd];
			if (is_array($doc)) {
				if (array_key_exists('@requireFlags', $doc)) {
					$requiredFlags = $doc['@requireFlags'][0];
				}
				if (array_key_exists('@allowedParams', $doc)) {
					$allowedParams = @$doc['@allowedParams'][0];
				}
			}
		}
		// Check flags
		if (is_string($requiredFlags)) {
			if (!WG::checkFlags($requiredFlags, false)) {
				echo "Required flag: " . $this->bold(TeamMember::flagname($requiredFlags)) . " ($requiredFlags)" . PHP_EOL;
				return false;
			}
		}
		// Check params
		if ($allowedParams != '' && !$this->checkModifiers($params, $allowedParams)) {
			return false;
		}
		return true;
	}
	
	
	public function autocomplete($str) {
		
		// On décompose les expressions de la commande
		$args = self::splitCommand("$str");

		// Tableau de retour
		$r = array();

		// Aucun argument = aucune proposition
		if (sizeof($args) < 1) {
			//$r['debug'] = 'No args';
			return $r;
		}

		// Un argument = c'est un nom de commande
		else if (sizeof($args) === 1) {
			$args = trim($args[0]);
			//$r['debug'] = "One arg: '$args'";
			if (empty($args)) {
				return $r;
			}
			$length = strlen($args);
			foreach ($this->commands as $method => $info) {
				// La commande commence par le châine demandée
				if (substr($method, 0, $length) == $args && strlen($method) > $length) {
					// On verifie les droits
					if (is_array($info) && isset($info['@requireFlags'])) {
						if (!WG::checkFlags($info['@requireFlags'][0], false)) {
							continue;
						}
					}
					// On ajoute la commande dans le tableau de retour, mais uniquement
					// les caractères qui complètent ce qui a été envoyé.
					$r[] = !$length ? $method : substr($method, $length);
				}
			}
		}

		// Plusieurs arguments = on regarde s'il existe une méthode pour gêrer l'autocompletion
		else if (method_exists($this, 'handle_' . $args[0] . '_autocomplete')) {
			// Mais avant on vérifie les ACLs
			$allowed = true;
			if (isset($this->commands[$args[0]])) {
				if (isset($this->commands[$args[0]]['@requireFlags'])) {
					$flags = $this->commands[$args[0]]['@requireFlags'][0];
					if (!WG::checkFlags($flags)) {
						$allowed = false;
					}
				}
			}
			if ($allowed) {
				try {
					call_user_func_array(array($this, 'handle_' . $args[0] . '_autocomplete'), array($args, &$r));
				}
				catch (Exception $ex) {
					//$r[] = $ex->getMessage();
					wgcrt_log_exception($ex);
				}
			}
		}
		
		// Dernier cas : auto-completion par défaut
		else if (method_exists($this, 'autocomplete_default')) {
			try {
				call_user_func_array(array($this, 'autocomplete_default'), array($args, &$r));
			}
			catch (Exception $ex) {
				wgcrt_log_exception($ex);
			}
		}
		
		// Maintenant on va faire une passe sur toutes les sorties, et regarder si elles commencent pas la même string
		// Autrement dit, la completion partielle.
		if (sizeof($r) > 1) {
			$length = 1;
			$prefix = '';
			while (true) {
				foreach ($r as $rs) {
					if ($length > strlen($rs)) {
						break 2;
					}
					$pre = substr($rs, 0, $length);
					if (strlen($prefix) < $length) {
						$prefix = $pre;
					}
					else if ($pre != $prefix) {
						break 2;
					}
				}
				$length++;
			}
			// Si un prefix commun a été remarqué, on l'ajoute dans la liste des propositions
			// en premier de la liste, et on l'entour de pipes.
			// Ensuite, c'est au client de gêrer la completion partielle.
			if (strlen($prefix) > 1) {
				// On met des pipes pour indiquer que ce n'est pas terminé
				array_unshift($r, '|' . substr($prefix, 0, -1) . '|');
			}
		}

		// A la fin, on renvoi la liste de sortie
		return $r;
	
	}
	
	/**
	 * Filtre pour l'autocompletion.
	 *
	 * Cette méthode permet de facilier la modification de la variable $r dans les méthodes
	 * de type 'handle_*_autocomplete'.
	 *
	 * @param string $needle Le mot qui sert de préfix de comparaison.
	 * @param string[] $haystack Liste des éléments à filtrer.
	 * @param string[] &$r Tableau de sortie.
	 */
	public function autocompleteFilter($needle, $haystack, &$r) {
		$length = strlen($needle);
		foreach ($haystack as $n) {
			if ($length === 0 || substr($n, 0, $length) === $needle) {
				$r[] = substr($n, $length);
			}
		}
	}
	
	/**
	 * Display help about commands.
	 * @cmdUsage ${cmdname} [command]
	 * @cmdHidden
	 */
	public function handle_help($file, $cmd, $params, $argv) {
	
		// Verification des modifiers
		if (!$this->checkModifiers($params, '')) {
			return false;
		}
	
		// Aide pour une commande en particulier
		if (isset($argv[0])) {
				
			// Command not found
			if (!method_exists($this, 'handle_' . $argv[0])) {
				echo "Command not found: " . $this->bold($argv[0]) . PHP_EOL;
				return false;
			}
			$doc = $this->getcmddoc($argv[0]);
				
			// Securité : on vérifie que l'utilisateur ai les droits sur cette commande
			if (isset($doc['@requireFlags'])) {
				if (!WG::checkFlags($doc['@requireFlags'][0])) {
					echo "Required flag: " . $this->bold($doc['@requireFlags'][0]) . PHP_EOL;
					return false;
				}
			}
			$help = '';
			if (isset($doc['@cmdUsage'])) {
				foreach ($doc['@cmdUsage'] as $usage) {
					$help .= 'Usage: ' . $usage . PHP_EOL;
				}
			}
			$help .= implode(PHP_EOL, $doc['@doc']);
			$help = str_replace(
					array('${cmdname}'),
					array($argv[0]),
					$help
			);
			echo $help . PHP_EOL;
				
			return true;
		}
	
		// Global help : liste des commandes
		echo "Available commands are:" . PHP_EOL;
	
		// Tableau de sortie
		$cmds = array('Commands' => array());
	
		// On parcours toutes les méthodes de l'api
		foreach ($this->commands as $func => $doc) {
	
			// Cette fonction n'est pas documentée : elle n'est pas affichée
			if (!is_array($doc)) {
				//echo "Debug notice: method 'handle_$func' is not documented." . PHP_EOL;
				continue;
			}
	
			// Cette méthode ne doit pas être affichée
			if (isset($doc['@cmdHidden'])) {
				continue;
			}
	
			// Securité : on vérifie que l'utilisateur ai les droits sur cette commande
			if (isset($doc['@requireFlags'])) {
				if (!WG::checkFlags($doc['@requireFlags'][0])) {
					continue;
				}
			}
	
			// Nom de package (nom de la pile)
			$stack = isset($doc['@cmdPackage']) ? $doc['@cmdPackage'][0] : 'Commands';
	
			if (!isset($cmds[$stack])) {
				$cmds[$stack] = array();
			}
	
			// On ajoute la commande à la pile
			$cmds[$stack][$func] = $doc;
	
		}
	
		// Gestion des alias
		// On parcours les piles (packages)
		foreach ($cmds as $name => $stack) {
			// On parcours les commandes
			foreach ($stack as $func => $cmd) {
				// La commande est en réalité une alias
				if (isset($cmd['@cmdAlias'])) {
					// On recupère le nom de la commande cible
					$alias = $cmd['@cmdAlias'][0];
					// La commande ciblée par l'alias est trouvée
					if (isset($stack[$alias])) {
						if (!isset($stack[$alias]['alias'])) {
							$cmds[$name][$alias]['alias'] = array();
						}
						// On associe l'alias à la commande cible
						$cmds[$name][$alias]['alias'][] = $func;
						// On supprime l'alias des comandes
						unset($cmds[$name][$func]);
					}
					// Sinon, on retire cette alias
					else {
						unset($cmds[$name][$func]);
					}
				}
			}
		}
	
		// Affichage des commandes
		foreach ($cmds as $name => $stack) {
			if (sizeof($stack) < 1) continue;
			echo PHP_EOL . $this->underline($name) . PHP_EOL;
			ksort($stack, SORT_STRING);
			foreach ($stack as $func => $cmd) {
				$alias = isset($cmd['alias']) ? ' | ' . implode(' | ', $cmd['alias']) : '';
				$pad = str_repeat(' ', 30 - strlen($alias) - strlen($func));
				$doc = isset($cmd['@doc'][0]) ? $cmd['@doc'][0] : '';
				echo " " . $this->bold($func) . "$alias$pad $doc" . PHP_EOL;
			}
		}
	
		echo PHP_EOL . 'Additionaly, a `cls` command should be provided by your shell.' . PHP_EOL;
		return true;
	}
	
}


if (!function_exists('str_getcsv')) {
	function str_getcsv($input, $delimiter = ',', $enclosure = '"', $escape = '\\', $eol = '\n') {
		if (is_string($input) && !empty($input)) {
			$output = array();
			$tmp    = preg_split("/".$eol."/",$input);
			if (is_array($tmp) && !empty($tmp)) {
				while (list($line_num, $line) = each($tmp)) {
					if (preg_match("/".$escape.$enclosure."/",$line)) {
						while ($strlen = strlen($line)) {
							$pos_delimiter       = strpos($line,$delimiter);
							$pos_enclosure_start = strpos($line,$enclosure);
							if (
									is_int($pos_delimiter) && is_int($pos_enclosure_start)
									&& ($pos_enclosure_start < $pos_delimiter)
							) {
								$enclosed_str = substr($line,1);
								$pos_enclosure_end = strpos($enclosed_str,$enclosure);
								$enclosed_str = substr($enclosed_str,0,$pos_enclosure_end);
								$output[$line_num][] = $enclosed_str;
								$offset = $pos_enclosure_end+3;
							} else {
								if (empty($pos_delimiter) && empty($pos_enclosure_start)) {
									$output[$line_num][] = substr($line,0);
									$offset = strlen($line);
								} else {
									$output[$line_num][] = substr($line,0,$pos_delimiter);
									$offset = (
											!empty($pos_enclosure_start)
											&& ($pos_enclosure_start < $pos_delimiter)
									)
									?$pos_enclosure_start
									:$pos_delimiter+1;
								}
							}
							$line = substr($line,$offset);
						}
					} else {
						$line = preg_split("/".$delimiter."/",$line);

						/*
						 * Validating against pesky extra line breaks creating false rows.
						*/
						if (is_array($line) && !empty($line[0])) {
							$output[$line_num] = $line;
						}
					}
				}
				return $output;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
}

?>