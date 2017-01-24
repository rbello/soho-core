<?php

namespace PHPonCLI;

trait Utils {
    
    public static function outputObject($o, $n = 1) {
    	$wb = "  ";
    	if (is_array($o)) {
    		if ($n > 1) return '[...]';
    		$str = "[\n";
    		$i = 0;
    		foreach ($o as $k => $v) {
    			if (is_array($v) || is_object($v)) $v = self::outputObject($v, $n + 1);
    			$str .= str_repeat($wb, $n) . /*str_pad("$k", 10, ' ') . ' = ' . */ $v . PHP_EOL;
    			if (++$i >= 100) {
    				$str .= str_repeat($wb, $n) . '...' . PHP_EOL;
    				break;
    			}
    		}
    		return $str . ']';
    	}
    	if ($o instanceof \DateTime) return $o->format('Y-m-d H:i:s');
    	if ($o instanceof \Exception) return get_class($o) . ' - ' . $o->getMessage();
    	$str = get_class($o);
    	$vars = get_object_vars($o);
    	$c = 0;
    	foreach ($vars as $k => $v) {
    		if (is_array($v) || is_object($v)) continue;
    		$c = max($c, strlen("$k"));
    	}
   		foreach ($vars as $k => $v) {
   			if (is_object($v)) {
   				if (in_array('__toString', get_class_methods($v)))
   					$v = $v->__toString();
   				else $v = self::outputObject($v, $n + 1);
   			}
   			$str .= PHP_EOL . str_repeat($wb, $n) . str_pad("$k", $c, ' ') . ' = ' . $v;
   		}
   		return $str;
    }
    
    /**
     * @deprecated
     */
    public static function reflections($instance, $method = null) {
    	$class = new \Wingu\OctopusCore\Reflection\ReflectionClass($instance);
    	if ($method != null) {
        	$method = $class->getMethod($method);
        	$doc = $method->getReflectionDocComment();
        	$annotations = $doc->getAnnotationsCollection();
        	return array($method, $annotations, $class, $doc);
    	}
    	$doc = $class->getReflectionDocComment();
        $annotations = $doc->getAnnotationsCollection();
    	return array($class, $annotations, $doc);
    }
    
	/**
	 * Apply trim on arrays
	 * 
	 * @param string[] $array
	 * @return string[]
	 */
	public static function atrim(array $array) {
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
	
	/**
	 * Explode a chain of command.
	 *
	 * This method ensures that:
	 *  - The space between the commands may be several white
	 *  - Parameters with spaces between quotes are supported
	 *    and parsed as a single argument.
	 *
	 * @param string $str
	 * @param boolean $trim
	 * @return string[]
	 */
	public static function split_commandline($str, $trim = true) {
		// Tokens are separated with white characters, they gather by standardizing
		// the separation of tokens with a single space, then we use a function that will
		// explode string taking into account the chains.
		return self::split_str(implode(' ', preg_split('/\s+/', $trim ? trim("$str") : "$str")), ' ');
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
	 * Based on parseParameters() function published on PHP.net by mbirth@webwriters.de
	 * @see http://fr.php.net/manual/en/function.getopt.php#83414
	 *
	 * @param array $params List of parameters. Left null mean $GLOBALS['argv'].
	 * @param array $all If FALSE, stop parsing parameters after the first command met.
	 * @param array $noopt List of parameters without values.
	 * @return string[]
	 */
	public static function parse_parameters($params = null, $all = true, $noopt = array(), $allowShort = true) {
	
		// Output array
		$result = array();
	
		// Global $argv
		if (!is_array($params)) {
			$params = $GLOBALS['argv'];
		}
	
		// Stop option parsing after the first non-option parameter met
		$stopOpt = false;
	
		// Could use getopt() here (since PHP 5.3.0), but it doesn't work relyingly
		reset($params);
		while (list($tmp, $p) = each($params)) {
			
			if (!is_string($p)) {
				$result[] = $p;
				continue;
			}
			
			if (strlen($p) < 1) continue;
				
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
	
	/**
	 * Split a string using str_getcsv.
	 * 
	 * Based on str_getcsv() function published on PHP.net by hpartidas@deuz.net   
	 * @see http://fr.php.net/manual/en/function.str-getcsv.php#98088
	 * 
	 * @param string $input The string to parse.
	 * @param string $delimiter Set the field delimiter (one character only).
	 * @param string $enclosure Set the field enclosure character (one character only).
	 * @param string $escape Set the escape character (one character only). Defaults as a backslash (\)
	 * @param string $eol Set the end-of-line character (one character only). Defaults as a new-line (\n)
	 */
	public static function split_str($input, $delimiter = ',', $enclosure = '"', $escape = '\\', $eol = '\n') {
		if (function_exists('str_getcsv')) {
			return str_getcsv($input, $delimiter, $enclosure, $escape);
		}
		$output = array();
		$tmp    = preg_split("/".$eol."/", $input);
		if (is_array($tmp) && !empty($tmp)) {
			while (list($line_num, $line) = each($tmp)) {
				if (preg_match("/".$escape.$enclosure."/", $line)) {
					while ($strlen = strlen($line)) {
						$pos_delimiter       = strpos($line, $delimiter);
						$pos_enclosure_start = strpos($line, $enclosure);
						if (
								is_int($pos_delimiter) && is_int($pos_enclosure_start)
								&& ($pos_enclosure_start < $pos_delimiter)
						) {
							$enclosed_str = substr($line,1);
							$pos_enclosure_end = strpos($enclosed_str, $enclosure);
							$enclosed_str = substr($enclosed_str, 0, $pos_enclosure_end);
							$output[$line_num][] = $enclosed_str;
							$offset = $pos_enclosure_end + 3;
						}
						else {
							if (empty($pos_delimiter) && empty($pos_enclosure_start)) {
								$output[$line_num][] = substr($line, 0);
								$offset = strlen($line);
							}
							else {
								$output[$line_num][] = substr($line, 0, $pos_delimiter);
								$offset = (
										!empty($pos_enclosure_start)
										&& ($pos_enclosure_start < $pos_delimiter)
								)
								? $pos_enclosure_start
								: $pos_delimiter + 1;
							}
						}
						$line = substr($line, $offset);
					}
				}
				else {
					$line = preg_split("/".$delimiter."/", $line);
					// Validating against pesky extra line breaks creating false rows.
					if (is_array($line) && !empty($line[0])) {
						$output[$line_num] = $line;
					}
				}
			}
			return $output;
		}
		return false;
	}
	
	/**
	 * Get the documentation of a method.
	 *
	 * @param object|string $class
	 * @param string $method
	 * @return string[]|null
	 * @throws Exception
	 */
	public static function get_method_doc($class, $method, $parent = null, &$redirect = array()) {
	
		try {
				
			$class = new \ReflectionClass(is_object($class) ? get_class($class) : $class);
				
			$method = $class->getMethod($method);
				
			if (!$method) {
				return null;
			}
				
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
				if (isset($r['@alias'])) {
					if (in_array($method, $redirect)) {
						throw new \Exception("Commands alias loop between '$method' and '$parent'");
					}
					$redirect[] = $method;
					$doc = self::get_method_doc($class, $r['@alias'][0], $method, $redirect);
					if (is_array($doc)) {
						$r = array_merge($r, $doc);
					}
				}
				return $r;
			}
		}
	
		catch (Exception $ex) {
		}
	
		return null;
	}
	
    
}