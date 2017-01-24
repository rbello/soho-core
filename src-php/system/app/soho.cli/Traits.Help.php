<?php

namespace PHPonCLI;

trait Help {
    
	protected function help_command($target) {
		
		// Command not found
		if (!array_key_exists($target, $this->commands)) {
			$this->output("Command not found: " . $this->bold($target));
			return false;
		}
		
		// Get command documentation
		$cmd = $this->commands[$target];
		
		//echo $cmd->getDocComment()->getOriginalDocBlock();
		
		// No help available
		/*if (!is_array($doc)) {
			$this->output("No help available for: " . $this->bold($argv[0]));
			return true;
		}*/
			
		// Check ACLs
		if ($cmd->hasAnnotationTag($this->ANNOTATION_PERMISSION)) {
			
			if (!$this->checkPermission(
				$this->getCurrentUserID(),
				$target,
				$cmd->getAnnotationTag($this->ANNOTATION_PERMISSION),
				'list')
			) {
				
				$this->output("Required permission.");
				// $this->bold($cmd->getAnnotationTag($this->ANNOTATION_PERMISSION))
				return false;
				
			}

		}
		
		// Output string
		$help = trim($cmd->getDocComment()->getShortDescription());

		// Usage(s)
		if ($cmd->hasAnnotationTag($this->ANNOTATION_USAGE)) {
			foreach ($cmd->getAnnotations()->getAnnotation($this->ANNOTATION_USAGE) as $usage) {
				$help .= PHP_EOL . 'Usage: ' . trim($usage->getDescription());
			}
		}
		
		// Documentation body
		if (!empty($cmd->getDocComment()->getLongDescription())) {
			$help .= PHP_EOL . $cmd->getDocComment()->getLongDescription();
		}
		
		// Replace
		$help = str_replace(
				array('${cmdname}'),
				array($target),
				$help
		);
		
		// Output the help
		$this->output($help);
		
		return true;
	}
	
	/**
	 * Display help about commands.
	 * 
	 * @usage ${cmdname} [command]
	 * @hidden This command is hidden from help list.
	 */
	public function handle_help($file, $cmd, array $params, array $argv, $pipedData, array &$context) {
	
		// Verification of parameters
		if (!$this->checkOptions($cmd, $params, '')) {
			return false;
		}
		
		// Help for a particular command
		if (isset($argv[0])) {
			return $this->help_command($argv[0]);
		}
	
		// Packages array
		$packages = array('Commands' => array());
		
		// Aliases array
		$aliases = array();
	
		// Fetch all commands
		foreach ($this->commands as $func) {
			
			// This function is currently not documented: not displayed
			if ($func->getDocComment()->isEmpty()) {
				trigger_error("Method '{$func->getPointerPath()}' is not documented.", E_USER_NOTICE);
				continue;
			}
	
			// This method should not be displayed
			if ($func->hasAnnotationTag($this->ANNOTATION_HIDDEN)) {
				continue;
			}
	
			// Security: verifies that the user have permissions on this command
			if ($func->hasAnnotationTag($this->ANNOTATION_PERMISSION)) {
				
				if (!$this->checkPermission(
					$this->getCurrentUserID(),
					$func->getName(),
					$func->getAnnotationTag($this->ANNOTATION_PERMISSION),
					'list')
				) {
					
					// Switch this command
					continue;
					
				}

			}
	
			// Package name (name of the stack)
			$package = $func->hasAnnotationTag($this->ANNOTATION_PACKAGE) ?
			           $func->getAnnotationTag($this->ANNOTATION_PACKAGE) :
			           'Command';

			// Create stack
			if (!isset($packages[$package])) {
				$packages[$package] = array();
			}
	
			// Add the command to the stack
			$packages[$package][$func->getName()] = $func;
	
		}
	
		// Managing aliases
		// Fetch packages
		foreach ($packages as $name => $commands) {

			// Fetch commands
			foreach ($commands as $func => $doc) {
				
				// This command in an alias
				if ($doc->hasAnnotationTag($this->ANNOTATION_ALIAS)) {
					
					// Retrieves the name of the target command
					$alias = $doc->getAnnotationTag($this->ANNOTATION_ALIAS);
					
					// Targeted command is found
					if (array_key_exists($alias, $this->commands)) {
						
						// Create alias table
						if (!isset($aliases[$alias])) {
							$aliases[$alias] = array();
						}
						
						// Bind alias to targeted command
						$aliases[$alias][] = $func;
					}
					
					// Else, display a notice
					else {
						trigger_error("Alias '$alias' not found, declared by '$func'", E_USER_NOTICE);
					}
					
					// Remove the alias
					unset($packages[$name][$func]);
				}
			}
		}
		
		// Global help : commands list
		$this->output('Available commands are:');
		
		// Affichage des commandes
		foreach ($packages as $name => $commands) {
			
			if (sizeof($commands) < 1) {
				continue;
			}
			
			$this->output('');
			$this->output($this->underline($name));
			
			ksort($commands, SORT_STRING);
			
			foreach ($commands as $func => $cmd) {
				$alias = isset($aliases[$func]) ? ' | ' . implode(' | ', $aliases[$func]) : '';
				$pad = str_repeat(' ', 30 - strlen($alias) - strlen($func));
				$doc = $cmd->getDocComment()->getShortDescription();
				$this->output(' ' . $this->bold($func) . "$alias$pad $doc");
			}
			
		}
	
		$this->output('');
		$this->output('Additionaly, a `cls` command should be provided by your shell.');
		return true;
	}
	
	/**
	 * Handle autocompletion for help command.
	 * 
	 * @param string[] $args
	 * @param string[] $suggestions
	 */
	public function handle_help_autocomplete(array $args, array &$suggestions) {
		
		if (sizeof($args) === 2) {
		
			$commands = array();
			
			foreach ($this->commands as $func => $data) {

				list($callback, $doc) = $data;
				
				if (isset($doc['@permission'])) {
					
					if (!$this->checkPermission(
						$this->getCurrentUserID(),
						$func,
						$doc['@permission'][0],
						'list')
					) {
						
						continue;
						
					}
					
				}
				
				$commands[] = $func;
				
			}
			
			if (sizeof($commands) > 0) {
				$this->autocompleteFilter(
					$args[1],
					$commands,
					$suggestions
				);
			}
				
		}
	}
    
}