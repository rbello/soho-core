<?php

// On a besoin de la configuration
require_once 'config.php';

// En fonction de la constante GENERATED_MODELS on va utiliser les modèles générés par Doctrine (/system/Models/),
// ou bien les modèles de base définis (/models/).
// Les modèles de base servent pour la génération automatique avec l'utilitaire de Doctrine 'orm:generate-entities'.
// Les modèles générés servent ensuite pour toute l'execution de l'application.
$modelsPaths = defined('GENERATED_MODELS') && GENERATED_MODELS === true ? array(BASE . '/system/Models') : array(BASE . '/models');

// On fabrique un driver
$driver = new Doctrine\ORM\Mapping\Driver\AnnotationDriver(
    new Doctrine\Common\Annotations\AnnotationReader(),
    $modelsPaths
);

$conf = \Doctrine\ORM\Tools\Setup::createAnnotationMetadataConfiguration($modelsPaths, $config['debug']);
$conf->setMetadataDriverImpl($driver);
#$conf->setProxyDir(BASE . '/system/Proxies');
#$conf->setProxyNamespace('EntityProxy');
#$conf->setAutoGenerateProxyClasses(true);

// On fabrique l'EntityManager
$em = \Doctrine\ORM\EntityManager::create(
    // Avec les paramètres de connexion donnés dans la configuration
    $config['persistence'],
    // Et la configuration spécifique du driver de méta-données
    $conf
);

// On ajoute un classloader automatique
#$classLoader = new \Doctrine\Common\ClassLoader('Entity', $modelsPaths[0]);
#$classLoader->register();
#$classLoader = new \Doctrine\Common\ClassLoader('EntityProxy', BASE . '/system/Proxies');
#$classLoader->register();

// Nettoyage
unset($conf, $modelsPaths, $classLoader);

// On renvoie l'EntityManager
return $em;
