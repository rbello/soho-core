<?php

namespace Soho\Packages;

class PkgContext {

    private $MANIFEST_FILE = "manifest.json";

    const TYPE_MULTI = 1;
    const TYPE_STRING = 1;
    const TYPE_BOOLEAN = 1;
    const TYPE_DOUBLE = 1;
    const TYPE_LONG = 1;

    private $ctx = array();
    private $handlers = array();

    public function __construct() {
    }

    /**
     * Add a handler.
     * The concept of 'handler' is described in the class comment.
     * 
     * @param string $pattern
     * @param Closure $handler
     * @return void
     */
    public function addHandler($pattern, \Closure $handler) {
        $this->handlers[$pattern] = $handler;
    }
    
    public function removeHandler($pattern) {
        unset($this->handlers[$pattern]);
    }

    /**
     * Get the all handlers
     * The concept of 'handler' is described in the class comment.
     * 
     * @param string $key
     * @return Closure[]
     */
    protected function getHandlersMatching($key) {
        $r = array();
        foreach ($this->handlers as $pattern => $handler) {
            if (fnmatch($pattern, $key)) {
                $r[] = $handler;
            }
        }
        return $r;
    }

    /**
     * Return the whole context loaded as array.
     * 
     * @return array
     */
    public function getContents() {
        return $this->ctx;
    }

    /**
     *  
     */
    public function loadInstalledPackage($directory) {
        self::scanPackage(basename($directory), $directory, $this);
    }

    /**
     * @param string $packagename
     * @param string $directory
     * @param PkgContext $context
     * @throws Exception
     */
    public static function scanPackage($packagename, $directory, PkgContext $context) {

        // Search manifest file
        $contents = file_get_contents("{$directory}/{$context->MANIFEST_FILE}");
        if (!$contents) {
            throw new \Exception("Unable to find manifest file for package: {$packagename} (expected: {$directory}/{$context->MANIFEST_FILE})");
        }

        // JSON string decode
        $contents = json_decode($contents, true, 10);
        if (!is_array($contents) || empty($contents)) {
            throw new \Exception("Invalid manifest file for package: {$packagename}");
        }

        // Fetch JSON manifest
        foreach ($contents as $key => $value) {
            foreach ($context->getHandlersMatching("manifest:{$key}") as $handler) {
                $handler($context, $packagename, "{$directory}/{$context->MANIFEST_FILE}", $key, $value);
            }
        }

        // Scan directories
        self::scanPackageDirectory($context, $packagename, $directory, true);
    }

    /**
     * 
     */
    protected static function scanPackageDirectory(PkgContext $context, $packagename, $directory, $root) {

        // Open package directory
        $handle = opendir($directory);
        if (!$handle) {
            throw new \Exception("Package directory is not readable: {$directory}");
        }

        // Fetch files
        while (false !== ($entry = readdir($handle))) {
            if ($entry == '.' || $entry == '..') continue;
            $f = "$directory/$entry";

            // Recursive scan
            if (is_dir($f)) {
                self::scanPackageDirectory($context, $packagename, $f, false);
            }

            // Check manifest file
            else if ($entry == 'manifest.json') {
                if (!$root) {
                    throw new \Exception("Manifest file must be in the package root directory: {$packagename}");
                }
            }

            // Check file with handlers
            else {
                foreach ($context->getHandlersMatching("file:{$entry}") as $handler) {
                    $handler($context, $packagename, $f, $entry);
                }
            }

        }
    }

    /**
     * 
     */
    public static function checkConfigurationItem(&$array, $filters, \Closure $cb = null) {
        // Check and fix JSON contents
        foreach ($array as $key => &$value) {
            // Filter only desired fields
            $value = array_intersect_key($value, $filters);
            // Give to handler
            if ($cb != null) $cb($value);
        }
    }

    /**
     * 
     */
    public function merge($itemname, $array) {
        // First time the item is added
        if (!array_key_exists($itemname, $this->ctx)) {
            $this->ctx[$itemname] = $array;
        }
        // Add news entries for this existing item
        else {
            // Check if given data don't contains an existing key
            $conflicts = array_intersect(array_keys($array), array_keys($this->ctx[$itemname]));
            if (sizeof($conflicts) > 0) {
                $conflicts = implode(', ', $conflicts);
                throw new \Exception("Attribute(s) '{$conflicts}' allready exists for item '{$itemname}' in execution context");
            }
            // Merge new data with older
            $this->ctx[$itemname] = array_merge($this->ctx[$itemname], $array);
        }
    }

}