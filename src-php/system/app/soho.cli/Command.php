<?php

namespace PHPonCLI;

use \Wingu\OctopusCore\Reflection as Reflection;

abstract class Command {
    
    protected $name;
    protected $doc;
    protected $annotations;
    
    function __construct($name, Reflection\ReflectionDocComment $doc) {
        $this->name = $name;
        $this->doc = $doc;
        $this->annotations = $doc->getAnnotationsCollection();
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function getDocComment() {
        return $this->doc;
    }
    
    public function getAnnotations() {
        return $this->annotations;
    }
    
    public function hasAnnotationTag($tag) {
        return $this->annotations->hasAnnotationTag($tag);
    }
    
    public function getAnnotationTag($tag) {
        return $this->annotations->getAnnotationTag($tag)->getDescription();
    }
    
    public function __toString() {
        return $this->name;
    }
    
    public abstract function execute(CommandHandler $hdr, $file, $cmd, array $params, array $argv, $pipedData, array &$context);
    
    public abstract function getPointerPath();
    
}

class MethodCommand extends Command {
    
    /**
     * @var object
     */
    protected $instance;
    
    /**
     * @var string
     */
    protected $methodName;
    
    /**
     * @var Reflection\ReflectionMethod
     */
    protected $method;
    
    public function __construct($name, $instance, $method) {
        $class = new \Wingu\OctopusCore\Reflection\ReflectionClass($instance);
        $this->method = $class->getMethod($method);
        $doc = $this->method->getReflectionDocComment();
        parent::__construct($name, $doc);
        $this->instance = $instance;
        $this->methodName = $method;
    }
    
    public function getMethod() {
        return $this->method;
    }
    
    public function execute(CommandHandler $hdr, $file, $cmd, array $params, array $argv, $pipedData, array &$context) {
        return call_user_func_array(
			array($this->instance, $this->methodName),
			array($file, $cmd, $params, $argv, $pipedData, &$context)
		);
    }
    
    public function getPointerPath() {
        return get_class($instance) . '::' . $this->methodName;
    }
    
    public function getMethodParameters() {
        return $this->method->getParameters();
    }
    
}

class CompositeCommand extends Command {
    
    protected $sub = array();
    
	public function __construct($cmdName) {
		parent::__construct($cmdName, $this->updateDocumentation());
	}
	
	public static function getUsage($name, Reflection\ReflectionMethod $method) {
	    $doc = "{$name}";
        foreach ($method->getParameters() as $param) {
            if ($param->isDefaultValueAvailable())
                $doc .= " [{$param->getName()}]";
            else
                $doc .= " <{$param->getName()}>";
        }
        return $doc;
	}
	
	protected function updateDocumentation() {
	    $doc = "/**\n * Manage {$this->getName()}s.\n *\n";
	    foreach ($this->sub as $name => $cmd) {
	        $doc .= " * @usage \${cmdname} " . self::getUsage($name, $cmd->getMethod()) . "\n";
	    }
	    $doc .= " */\n";
	    $this->doc = new Reflection\ReflectionDocComment($doc);
	    $this->annotations = $this->doc->getAnnotationsCollection();
	    return $this->doc;
	}
	
	public function addSubCommand($name, $instance, Reflection\ReflectionMethod $method) {
		//echo "+{$this->getName()} {$name} --> ".get_class($instance)."::{$method->getName()}()\n";
		$this->sub[$name] = new SubCommand($name, $instance, $method->getName());
		$this->updateDocumentation();
	}
	
    public function execute(CommandHandler $hdr, $file, $cmd, array $params, array $argv, $pipedData, array &$context) {
        if (empty($argv)) {
            return $hdr->redirect('help', $this->getName());
        }
        $sub = array_shift($argv);
        if (!array_key_exists($sub, $this->sub)) {
            $hdr->output("Command not found: {$sub}");
            return false;
        }
        $r = $this->sub[$sub]->execute($hdr, $file, $cmd, $params, $argv, $pipedData, $context);
        
        $hdr->output($r);
		return true;
    }
    
    public function getPointerPath() {
        return '<multiple>';
    }
	
}

class SubCommand extends MethodCommand {
    /**
     * @override
     */
    public function execute(CommandHandler $hdr, $file, $cmd, array $params, array $argv, $pipedData, array &$context) {
        // Remove sub command name
        $sub = array_shift($params);
        // Count arguments
        $ps = $this->method->getParameters();
        $required = 0;
        $max = 0;
        foreach ($ps as $p) {
            $max++;
            if (!$p->isDefaultValueAvailable()) $required = $max;
        }
        // Check arguments count
        $c = sizeof($params);
        if ($c < $required) {
            $hdr->output("Missing argument ".($c+1)." (".$ps[$c]->getName().")");
            $hdr->output("Usage: " . CompositeCommand::getUsage("{$cmd} {$sub}", $this->method));
            return false;
        }
        if ($c > $max) {
            $hdr->output("Expected {$required} arguments, {$c} given");
            $hdr->output("Usage: " . CompositeCommand::getUsage("{$cmd} {$sub}", $this->method));
            return false;
        }
        // Check arguments types
        
        
        // TODO checkPermission
        
        return capture(
            // Code to try
            function () use ($params) {
                return call_user_func_array(
        			array($this->instance, $this->methodName),
        			$params
        		);
            },
            // Catch exceptions and error reporting
            function (\Exception $ex) use ($hdr) {
                $hdr->output(get_class($ex) . ': ' . $ex->getMessage());
                return false;
            });

    }
}