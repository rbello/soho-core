<?php

namespace PHPonCLI;

require_once 'Traits.Utils.php';
require_once 'Traits.Style.php';
require_once 'Traits.Help.php';
require_once 'Command.php';

/**
 * A single abstract class to create a Command Line Interface (CLI) with PHP.
 * 
 * - Full featured opts/args parser
 * - Simply add commands by adding a new method to the class
 * - Spelling suggestions
 * - Output colored/styled messages
 * - Command auto-completion and glob support
 * - Help generated from methods documentations
 * - Ability to "pipe" the output of one command in as input to another
 * 
 * Usage:
 * 		require file PHPonCLI.php
 * 		Create a class extending PHPonCLI
 * 		Implements 2 methods: getCurrentUserID() and checkPermission()
 * 		Create a new instance, and execute with exec($GLOBALS['argv'])
 * 
 * @author remi.bello.pro@gmail.com
 * @link https://github.com/rbello
 */
abstract class CommandHandler {
	
	use Styles;
	use Utils;
	use Help;

	/**
	 * Library version.
	 * 
	 * @var string
	 */
	const VERSION = '1.15';
	
	    /**
	 * Don't decorate outputs.
	 * 
	 * @var int
	 */
	const DECORATION_NONE  = 10;
	
	/**
	 * Decorate outputs with HTML markup.
	 * 
	 * @var int
	 */
	const DECORATION_HTML  = 11;
	
	/**
	 * Decorate outputs with AINSI codes.
	 * 
	 * @var int
	 * @see http://pueblo.sourceforge.net/doc/manual/ansi_color_codes.html
	 */
	const DECORATION_AINSI = 12;
	
	public $ANNOTATION_COMMAND     = 'cli';
	public $ANNOTATION_PERMISSION  = 'permission';
	public $ANNOTATION_HIDDEN      = 'hidden';
	public $ANNOTATION_PACKAGE     = 'package';
	public $ANNOTATION_ALIAS       = 'alias';
	public $ANNOTATION_USAGE       = 'usage';
	
	/**
	 * Result of the last execution.
	 * @var mixed[]
	 */
	public $result = null;
	
	/**
	 * Enable command piping.
	 * @var boolean
	 */
	public $enablePipes = true;
	
	/**
	 * List of available commands.
	 * @var mixed[]
	 */
	protected $commands = array();
	
	/**
	 * Decoration type of returned content.
	 * @var Closure|null
	 */
	protected $decorator = null;
	
	/**
	 * TODO
	 * @var boolean
	 */
	protected $printOuputs = true;
	
	/**
	 * TODO
	 * @var string[]
	 * @see PHPonCLI::$printOuputs
	 */
	protected $output = array();
	
	/**
	 * Constructor.
	 */
	public function __construct() {
		// Internal commands initialization
		$this->addObject($this);
	}
	
	/**
	 * Add all methods of an object.
	 * 
	 * To be mapped, a method have to :
	 * 	- Respect the name convention 'handle_<commandName>'
	 *      e.g.  handle_exit()
	 *  - Have the @CLI annotation
	 */
	public function addObject($instance) {
		// Fetch methods
		foreach (get_class_methods($instance) as $method) {
			// Name convention
			if (substr($method, 0, 7) === 'handle_' && substr($method, -13) != '_autocomplete') {
				$this->addMethod(substr($method, 7), $instance, $method);
			}
			// Annotation
			else {
				list($method, $annotations, $class, $doc) = self::reflections($instance, $method);
				if (!$annotations->hasAnnotationTag($this->ANNOTATION_COMMAND))
					continue;
				$annotation = $annotations->getAnnotation($this->ANNOTATION_COMMAND)[0];
				// Automatic mode
				if (is_null($annotation->getDescription()))
					$this->addAutomaticCommand($instance, $method, $doc);
				// Annoted informations
				else
					$this->addAnnotationCommand($instance, $method, $doc);
			}
		}
	}
	
	private function addAutomaticCommand($instance, $m, $doc) {
		$name = $m->getName();
		// Setters
		if (substr($name, 0, 3) == 'get') {
			$name = substr($name, 3);
			// Split
			@list($name, $attribut) = explode('By', $name);
			// Plural (0..N)
			if (substr($name, -1) == 's') {
				$type = strtolower(substr($name, 0, 1)) . substr($name, 1, -1);
				$cmd = 'get-all';
			}
			// Singular (0..1)
			else {
				$type = strtolower(substr($name, 0, 1)) . substr($name, 1);
				$cmd = 'get';
			}
			if (isset($attribut)) {
				$cmd .= '-' . strtolower($attribut);
			}
			$func = $this->getCommand($type);
			if ($func == null) {
				$func = new CompositeCommand($type);
				$this->addCommand($func);
			}
			$func->addSubCommand($cmd, $instance, $m);
		}
		// 
		else {
			$this->addMethod($m->getName(), $instance, $m->getName());
		}
	}
	
	/**
	 * Add a command.
	 * 
	 * @param string $name Name of the CLI command.
	 * @param object $instance Instance of the object to invoke method.
	 * @param string $method Method name.
	 * @throws Exception
	 */
	public function addMethod($name, $instance, $method) {
		$this->commands[$name] = new MethodCommand($name, $instance, $method);
	}
	
	public function addCommand(Command $cmd) {
		$this->commands[$cmd->getName()] = $cmd;
	}
	
	public function getCommand($name) {
		return array_key_exists($name, $this->commands) ? $this->commands[$name] : null;
	}
	
	#####
	#####   T O   O V E R R I D E
	#####
	
	/**
	 * 
	 * 
	 * @return null|string|int|mixed
	 */
	public abstract function getCurrentUserID();
	
	/**
	 * 
	 * @param string|int|mixed $userID
	 * @param string $object
	 * @param string $permission
	 * @param string $action
	 * @return boolean
	 */
	public abstract function checkPermission($userID, $object, $permission, $action);
	
	/**
	 * Write a string to the standard output. 
	 * 
	 * @param string $str The string to write.
	 * @param boolean $nl Append with a new line.
	 * @stdout string
	 */
	public function output($str, $nl = true) {
		if (is_object($str) || is_array($str)) {
			//$str = json_encode($str, JSON_PRETTY_PRINT);
			$str = self::outputObject($str);
		}
		if ($this->printOuputs) {
			echo $str . ($nl ? PHP_EOL : '');
		}
		else {
			if ($nl || sizeof($this->output($str) === 0)) {
				$this->output[] = $str;
			}
			else {
				$this->output[sizeof($this->output) - 1] .= $str;
			}
		}
	}
	
	#####
	#####   E X E C U T I O N
	#####
	
	/**
	 * Execute a command.
	 * 
	 * The $args var can be a string or an array of strings.
	 * 
	 * The method returns a boolean to indicate if the execution
	 * is successful. The full results and the return code are
	 * stored in $this->result variable.
	 * 
	 * All data are sent to standard output, even error messages.
	 * If the requested command is not found, suggestions for command
	 * names will be proposed using metaphone and levenshtein
	 * approximations.
	 * 
	 * To easily integrate a new order, just create a method with
	 * prefixed by "handle_". This method must be documented to manage
	 * ACLs. For details, see the documentation of "handle_help" method.
	 * 
	 * An ACL verification system is turned on if the method
	 * corresponding to the invoked command is documented, and declares
	 * a "@permission" doccomment. In this case, the method $this->checkACL()
	 * is called. This method is abstract by default and must be implemented.
	 * 
	 * The command format should be:
	 *  <COMMAND_NAME> [-option VALUE] [--longoption=VALUE] [ARGS...]
	 *  
	 * Important:
	 * The first argument of $argv must be the name of the executed PHP
	 * script. This is the default behavior when using PHP in CLI: the
	 * path to the script is placed first in $argv. To execute a command
	 * directly without going through the CLI, you must manually add the path.
	 * Eg. $shell->exec(__FILE__ . ' ' . $myCommandString);
	 * 
	 * @param string|string[] $argv
	 * @param boolean $return If you would like to capture the output of exec(),
	 * 	use the return parameter. When this parameter is set to TRUE,
	 *  exec() will return the information rather than print it.
	 * @param string[]|null Data piped to the command.
	 * @return boolean|string[] According to $return
	 * @stdout string
	 * @throws Exception
	 */
	public function exec($argv, $return = false, $pipedData = null) {
		
		// Set print flag
		$this->printOuputs = !$return;

		// Reset output array
		$this->output = array(); 
		
		// Default state
		$this->result = array(
			'failure' => false,
			'returnCode' => 0
		);

		// Split arguments
		if (!is_array($argv)) {
			$argv = self::split_commandline("$argv");
		}

		// Check arguments
		if (sizeof($argv) < 2) {

			// Save the error state
			$this->result = array(
				'failure' => true,
				'errorMsg' => 'Invalid query string (arguments missing)',
				'returnCode' => 50
			);
			
			// Display the error message
			$this->output("Usage: {$argv[0]} CMD [PARAM...]");

		}
		
		else {
			
			// Pipes array
			$piped = array();
			
			// Explode piped parts
			if ($this->enablePipes && in_array('|', $argv)) {
				// Split query string
				foreach (array_reverse(array_keys($argv, '|')) as $key) {
					$pipe = array_slice($argv, $key, null, true); // get pipe part
					array_splice($argv, $key); // remove the part
					array_shift($pipe); // remove pipe caractere
					$piped[] = $pipe;
				}
			}
			
			// Debug
			//echo '[Execute =>'; foreach ($argv as $t) echo ' ' . escapeshellarg("$t"); echo ']' . PHP_EOL;
	
			// Parse parameters
			$params = self::parse_parameters($argv);
	
			// Shift parameters
			$file = array_shift($params);
			$cmd  = array_shift($params);
			array_shift($argv);
			array_shift($argv);

			// Search for command
			if (!array_key_exists($cmd, $this->commands)) {
				
				// Save the error state
				$this->result = array(
					'failure' => true,
					'errorMsg' => "Command not found: $cmd",
					'returnCode' => 54
				);
				
				// Command is not found
				$this->output("Command not found: " . $this->bold($cmd));
				
				// Suggestions array
				$suggest = array();
				
				// Calculate command mataphone key
				$metaphone = metaphone($cmd);
				
				// Fetch available commands
				foreach ($this->commands as $method => $func) {
					
					// Security: don't provide commands the user do not have the
					// right to use.
					if ($func->hasAnnotationTag($this->ANNOTATION_PERMISSION)) {
						if (!$this->checkPermission(
							$func->getCurrentUserID(),
							$method,
							$func->getAnnotation($this->ANNOTATION_PERMISSION),
							'list'
						)) {
							continue;
						}
					}
					
					// Syntactic correspondence
					// @see https://en.wikipedia.org/wiki/Levenshtein_distance
					if (levenshtein($cmd, $method) === 1) {
						$suggest[] = $method;
					}
					
					// Sound correspondence
					// @see https://en.wikipedia.org/wiki/Metaphone
					else if (metaphone($method) == $metaphone) {
						$suggest[] = $method;
					}
					
				}
				
				// Display suggestions
				if (sizeof($suggest) > 0) {
					$this->output("Did you mean '" . implode("' or '", $suggest) . "' ?");
				}
				
			}
			
			else {
	
				// Before starting the command, disable outputs and turn styles off
				if (sizeof($piped) > 0) {

					// Disable outputs
					$this->printOuputs = false;
					
					// Before piping, disable styles
					$decorator = $this->decorator;
					$this->decorator = null;
				
				}
				
				// Context
				$context = array();
				
				// Get data
				//list($callback, $doc) = $this->commands[$cmd];
				$cmd = $this->commands[$cmd];
				
				// Execute the command
				try {
					$result = $cmd->execute($this, $file, $cmd, $params, $argv, $pipedData, $context);
					/*$result = call_user_func_array(
						//$callback,
						array($callback->getPointer(), 'execute'),
						array($file, $cmd, $params, $argv, $pipedData, &$context)
					);*/
				}
				// Catch failures
				catch (Exception $ex) {
					
					// Save the error state
					$this->result = array(
						'failure' => true,
						'errorMsg' => $ex->getMessage(),
						'errorEx' => $ex,
						'returnCode' => 51
					);
					
					// Output the error message
					$this->output("$cmd: {$ex->getMessage()}");
					
					// Return the failure flag
					return false;
				}
				
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
				
				// Merge result with context variable, which allows the
				// contents of the result to be overrided.
				$this->result = array_merge($this->result, $context);
			
			}
			
		}
		
		// Piping process
		if (isset($piped) && sizeof($piped) > 0) {

			// If the first command failed, all outputs are stored and should be printed 
			if ($this->result['failure']) {
				echo implode(PHP_EOL, $this->output) . PHP_EOL;
				// Disable piping
				return false;
			}
			
			// Fetch pipe parts
			while ($next = array_pop($piped)) {
				// Debug
				//echo 'Pipe to ' . $next[0] . ': ' . print_r($this->output, true). PHP_EOL;
				// Prepend with file path
				array_unshift($next	, $file);
				// Restore decorator for last piped command
				if (sizeof($piped) < 1 && isset($decorator)) {
					$this->decorator = $decorator;
				}
				// Execute next command
				$result = $this->exec($next, true, $this->output);
				// Failure
				if (!is_array($result)) {
					// Stop piping
					break;
				}
			}
			
			// Print outputs
			if (!$return) {
				echo implode(PHP_EOL, $this->output) . PHP_EOL;
			}
		}
		
		// Return the output array
		if ($return) {
			return $this->output;
		}
		// Return success or failure flag
		else {
			return !$this->result['failure'];
		}

	}
	
	public function redirect($cmd) {
		$args = func_get_args();
		array_unshift($args, __FILE__);
		return $this->exec($args);
	}
	
	#####
	#####   V E R I F I C A T I O N   P R O C E S S
	#####

	/**
	 * This method whill automatically check what permissions are required and
	 * the options passed when calling a command. It must be called in
	 * "handle_" methods that manage commands.
	 * 
	 * Used without arguments, this method will automatically set options and
	 * permissions required by the command, and verify from the data supplied
	 * to the "handle_" method of the command.
	 * 
	 * /**
	 *  * @permission hello
	 *  * @options hi
	 *  * /
	 * function handle_hello($file, $cmd, $params, $argv) {
	 * 		if ($this->check()) {
	 * 			return false;
	 * 		}
	 * 		$this->output(isset($params['hi']) ? 'Hi!' : 'Hello!');
	 * 		return true; 
	 * }
	 * 
	 * @param string $allowedOptions Options list, separated by coma.
	 * @param string $requiredPermission ACL permission required to execute the command.
	 * @return boolean 
	 */
	public function check($allowedOptions = '', $requiredPermission = null) {
		
		// Invocation data
		$traces = debug_backtrace();
		$traces = $traces[1];
		$cmd = substr($traces['function'], 7);
		$cmdinput = $traces['args'][1];
		$params = $traces['args'][2];
		$argv = $traces['args'][3];
		
		// No arguments passed to this methods : automatic mode
		if ($allowedOptions == '' || !is_string($requiredPermission)) {

			// Get method documentation
			list($callback, $doc) = $this->commands[$cmd];

			// If a documenation is provided
			if (is_array($doc)) {
				if (array_key_exists('@permission', $doc)) {
					$requiredPermission = @$doc['@permission'][0];
				}
				if (array_key_exists('@options', $doc)) {
					$allowedOptions = @$doc['@options'][0];
				}
			}
		}
		
		// Check permission
		if (is_string($requiredPermission)) {
			
			// Check 
			$ok = $this->checkPermission(
				$this->getCurrentUserID(),	// Current user ID
				$cmd,						// Command name (the object)
				$requiredPermission,		// Required permission
				'exec'						// Action
			);
			
			// Failure
			if (!$ok) {
				
				// Save the error state
				$this->result = array(
					'failure' => true,
					'errorMsg' => "Required permission: $requiredPermission",
					'returnCode' => 52
				);
			
				// Output a message
				$this->output("Required permission: " . $this->bold($requiredPermission));
				
				// Return the failure flag
				return false;
				
			}
		}
		
		// Check params
		if (!$this->checkOptions($cmd, $params, $allowedOptions)) {
			// Return the failure flag
			return false;
		}
		
		// Return the success flag
		return true;
	}
	
	/**
	 * Check command options.
	 * 
	 * @param string $cmd
	 * @param string[] $params
	 * @param string[]|string $modifiers
	 * @return boolean
	 */
	public function checkOptions($cmd, array $params, $options) {
		
		// Split options list
		if (!is_array($options)) {
			$options = preg_split('/\s+/', "$options");
		}
		
		// Fetch parameters
		foreach ($params as $k => $v) {
			
			// Ignore non-option parameters
			if (is_int($k)) {
				continue;
			}

			// Unauthorized option
			if (!in_array($k, $options)) {
				
				// Save the error state
				$this->result = array(
					'failure' => true,
					'errorMsg' => "Invalid option: $k",
					'returnCode' => 53
				);
				
				// Display an error message
				$this->output("$cmd: invalid option '" . $this->bold($k) . "'");
				
				// Return a failure flag
				return false;
			}
		}
		
		// Return a success flag
		return true;
	}
	
	#####
	#####   A U T O - C O M P L E T E
	#####
	
	/**
	 * 
	 * @param string $str
	 * @return string[]
	 */
	public function autocomplete($str) {
		
		// Explodes command expression
		$args = self::split_commandline("$str", false);

		// Output array
		$r = array();

		// Empty argument
		if (sizeof($args) < 1) {
			return $r;
		}

		// One argument only = it's a command name
		else if (sizeof($args) === 1) {

			$args = trim($args[0]);
			$length = strlen($args);
			
			if (empty($args)) {
				return $r;
			}
			
			foreach ($this->commands as $method => $info) {

				// Command begins with the required chain
				if (substr($method, 0, $length) == $args && strlen($method) > $length) {

					// Permissions verification
					if (is_array($info) && isset($info['@permission'])) {
						
						// Forbidden : switch this command
						if (!$this->checkPermission(
							$this->getCurrentUserID(),
							$method,
							$info['@permission'][0],
							'list'
						)) {
							continue;
						}
						
					}
					
					// Adds the command in the returned array, but only the characters
					// that complement what has been sent.
					$r[] = !$length ? $method : substr($method, $length);
					
				}
			}
		}

		// Several arguments = looking at whether there is a method to handle the autocompletion
		else if (method_exists($this, 'handle_' . $args[0] . '_autocomplete')) {
			
			// Check ACLs first
			$allowed = true;
			if (isset($this->commands[$args[0]])) {
				
				// Command exists and has a permission field
				if (isset($this->commands[$args[0]]['@permission'])) {
					
					$perm = $this->commands[$args[0]]['@permission'][0];
					
					if (!$this->checkPermission(
						$this->getCurrentUserID(),
						$args[0],
						$perm,
						'autocomplete'
					)) {
						$allowed = false;
					}
					
				}
			}
			
			// If user is allowed to run this command, autocomplete method is called 
			if ($allowed) {
				try {
					call_user_func_array(
						array($this, 'handle_' . $args[0] . '_autocomplete'),
						array($args, &$r)
					);
				}
				catch (Exception $ex) {
					trigger_error(get_class($ex) . " thrown in PHPonCLI::autocomplete()", E_USER_WARNING);
				}
			}
		}
		
		// Now we are going to pass on all outputs, and see if they start with
		// the same string. In other words, the partial completion.
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
			
			// If a common prefix has been noticed, it is added first to the list
			// of suggested in the list, and round pipes.
			// Then, the client has to manage the partial completion.
			if (strlen($prefix) > 1) {
				// We put pipes to indicate that the string is not terminated
				array_unshift($r, '|' . substr($prefix, 0, -1) . '|');
			}
		}

		// At the end, we return the output list
		return $r;
	
	}
	
	/**
	 * Autocompletion filter.
	 *
	 * This method facilitates the modification of the variable return
	 * type methods 'handle_*_autocomplete'.
	 *
	 * @param string|string[] $needle The word used to prefix comparison.
	 * @param string[] $haystack List of items to filter.
	 * @param string[] &$r Output array.
	 */
	public function autocompleteFilter($needle, array $haystack, array &$r) {
		if (is_array($needle)) {
			$needle = '' . array_pop($needle);
		}
		$length = strlen($needle);
		foreach ($haystack as $n) {
			if ($length === 0 || substr($n, 0, $length) === $needle) {
				$r[] = substr($n, $length);
			}
		}
	}

}