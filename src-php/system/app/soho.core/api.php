<?php

// Ensure access via /api/ url, not directly with /system/api.php
if (substr($_SERVER['REQUEST_URI'], 0, 5) != '/api/') {
    header('HTTP/1.0 403 Forbidden');
    echo "403 Forbidden";
    exit();
}

// Bootstrap
include_once __DIR__ . '/bootstrap.php';

// Liste des APIs disponibles
//$apis = array();
//foreach (glob(BASE . 'api/*') as $k => &$v) { $x = str_replace('.php', '', basename($v)); $apis[strtolower($x)] = $x; }
foreach ($list = glob(BASE . 'api/*.php') as $k => &$v) $v = str_replace('.php', '', basename($v));

// Read client input data
@list($dn, $api, $type, $method) = explode(' ', $_REQUEST['x--dn'], 4);
unset($_REQUEST['x--dn']);

function output_json($value) {
    $r = array('rsp' => 200, 'typ' => get_type($value), 'val' => $value);
    return json_encode($r);
}

// On recherche l'API
if (!in_array($api, $list)) {
    header('HTTP/1.0 404 Not Found');
    echo "404 Api Not Found ({$api})";
    exit();
}

// On inclus la classe
include_once BASE . "api/{$api}.php";

// On recherche l'implémentation
if (!class_exists("\API\\{$api}")) {
    header('HTTP/1.0 500 Api Not Found');
    echo "500 Api Not Available ({$api})";
    exit();
}

$apiClass = "\API\\{$api}";

$url = "{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['SERVER_NAME']}/api/";

// Génération du WSDL
if ($dn == 'wsdl') {
    
    // TODO Vérifier la méthode (GET)
    
    //header("Content-type: application/wsdl+xml");
    header("Content-type: text/xml");
    
    // On construit la classe de conversion
    $wsdl = new \PHP2WSDL\PHPClass2WSDL($apiClass, "{$url}{$api}.soap");
    
    // On lance la génération du WSDL
    $wsdl->generateWSDL(true);
    $data = $wsdl->dump();
    echo $data;
    
    // Cache
    @file_put_contents(BASE . "system/cache/wsdl/{$api}.wsdl", $data);
    
}

// Execution de la requête
else if ($dn == 'soap') {
    ini_set('soap.wsdl_cache_enabled', $config['debug'] ? '0' : '1');
    ini_set('soap.wsdl_cache_ttl', '86400');
    ini_set('soap.wsdl_cache_dir', BASE . 'system/cache/wsdl/');
    $server = new \SoapServer("{$url}{$api}.wsdl", array(
        /*'style'     => SOAP_DOCUMENT,
        'use'       => SOAP_LITERAL,*/
        'trace'     => $config['debug'] ? 1 : 0,
		'exception' => $config['debug'] ? 1 : 0
    ));
    $server->setObject(api($api));
    $server->handle();
}

else if ($dn == 'info') {
    header("Content-type: text/html");
    echo "<html><head><title>{$api}</title></head><body><h1>{$api}</h1><ul>";
    echo "<li><a href='/api/{$api}.wsdl'>WSDL</a></li>";
    echo "<li><a href='/api/{$api}.soap'>SOAP</a></li>";
    echo "<li><a href='/api/{$api}/test.json'>REST</a></li>";
    echo "</ul></body>";
}

else if ($dn == 'rest') {
    
    try {
       $reflector = new \ReflectionMethod($apiClass, $method);
    }
    catch (\Exception $ex) {
        header('HTTP/1.0 404 Method Not Found');
        echo "404 Method Not Found ({$api}::{$method})";
        exit();
    }

    // Vérification des arguments
    $args = array();
    foreach ($reflector->getParameters() as &$param) {
        // Le paramétre n'est pas spécifié
        if (!array_key_exists($param->getName(), $_REQUEST)) {
            // Valeur par défaut
            if ($param->isDefaultValueAvailable()) {
                $args[$param->getName()] = $param->getDefaultValue();
            }
            // Erreur
            else {
                header('HTTP/1.0 400 Bad Request');
                echo "400 Expected Argument Not Specified ({$param->getName()})";
                exit();
                continue;
            }
        }
        // On garde le paramétre pour l'appel
        else {
            $args[$param->getName()] = $_REQUEST[$param->getName()];
            unset($_REQUEST[$param->getName()]);
        }
    }
    if (!empty($_REQUEST)) {
        header('HTTP/1.0 400 Bad Request');
        $k = array_keys($_REQUEST);
        echo "400 Invalid Argument ({$k[0]})";
        exit();
    }
    
    // TODO try catch
    $out = $reflector->invokeArgs(api($api), $args);
    
    // TODO Type en fonction + ajouter XML + CSV
    header("Content-type: application/json");
    echo output_json($out);
    
}


// Error
else {
    header('HTTP/1.0 400 Bad Request');
    echo "400 Bad Request (dn={$dn})";
    exit();
}